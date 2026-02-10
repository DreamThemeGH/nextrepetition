<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — TTS Service (reused from v1)
 */

namespace OCA\Flashcards\Service;

use OCA\Flashcards\AppInfo\Application;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use Psr\Log\LoggerInterface;

class TTSService {

    private const CACHE_FOLDER = 'tts_cache';

    private const DEFAULT_VOICES = [
        'en' => 'en-US-AriaNeural',
        'en-US' => 'en-US-AriaNeural',
        'en-GB' => 'en-GB-SoniaNeural',
        'ru' => 'ru-RU-SvetlanaNeural',
        'ru-RU' => 'ru-RU-SvetlanaNeural',
        'de' => 'de-DE-KatjaNeural',
        'de-DE' => 'de-DE-KatjaNeural',
        'fr' => 'fr-FR-DeniseNeural',
        'fr-FR' => 'fr-FR-DeniseNeural',
        'es' => 'es-ES-ElviraNeural',
        'es-ES' => 'es-ES-ElviraNeural',
        'it' => 'it-IT-ElsaNeural',
        'it-IT' => 'it-IT-ElsaNeural',
        'pt' => 'pt-BR-FranciscaNeural',
        'pt-BR' => 'pt-BR-FranciscaNeural',
        'ja' => 'ja-JP-NanamiNeural',
        'ja-JP' => 'ja-JP-NanamiNeural',
        'zh' => 'zh-CN-XiaoxiaoNeural',
        'zh-CN' => 'zh-CN-XiaoxiaoNeural',
        'ko' => 'ko-KR-SunHiNeural',
        'ko-KR' => 'ko-KR-SunHiNeural',
        'sr' => 'sr-RS-SophieNeural',
        'sr-RS' => 'sr-RS-SophieNeural',
        'hr' => 'hr-HR-GabrijelaNeural',
        'hr-HR' => 'hr-HR-GabrijelaNeural',
        'uk' => 'uk-UA-PolinaNeural',
        'uk-UA' => 'uk-UA-PolinaNeural',
        'pl' => 'pl-PL-AgnieszkaNeural',
        'pl-PL' => 'pl-PL-AgnieszkaNeural',
        'tr' => 'tr-TR-EmelNeural',
        'tr-TR' => 'tr-TR-EmelNeural',
        'ar' => 'ar-SA-ZariyahNeural',
        'ar-SA' => 'ar-SA-ZariyahNeural',
        'hi' => 'hi-IN-SwaraNeural',
        'hi-IN' => 'hi-IN-SwaraNeural',
    ];

    public function __construct(
        private IAppData $appData,
        private LoggerInterface $logger,
    ) {
    }

    public function synthesize(string $text, string $language = 'en-US', ?string $voice = null): array {
        $text = trim($text);
        if (empty($text)) {
            throw new \InvalidArgumentException('Text cannot be empty');
        }

        if (mb_strlen($text) > 500) {
            $text = mb_substr($text, 0, 500);
        }

        $voiceName = $voice ?? $this->resolveVoice($language);
        $cacheId = $this->generateCacheId($text, $voiceName);

        if ($this->hasCached($cacheId)) {
            return ['id' => $cacheId, 'cached' => true, 'mimeType' => 'audio/mpeg'];
        }

        $audioData = $this->edgeTTS($text, $voiceName);
        $this->storeInCache($cacheId, $audioData);

        return ['id' => $cacheId, 'cached' => false, 'mimeType' => 'audio/mpeg'];
    }

    public function getAudio(string $cacheId): string {
        $folder = $this->getCacheFolder();
        try {
            $file = $folder->getFile($cacheId . '.mp3');
            return $file->getContent();
        } catch (NotFoundException) {
            throw new NotFoundException("Audio file not found: {$cacheId}");
        }
    }

    public function getVoices(): array {
        $voices = [];
        foreach (self::DEFAULT_VOICES as $langCode => $voiceName) {
            if (strlen($langCode) <= 2) continue;
            $parts = explode('-', $langCode);
            $voices[] = [
                'id' => $voiceName,
                'name' => $voiceName,
                'language' => $langCode,
                'languageName' => $this->getLanguageName($parts[0]),
                'gender' => str_contains($voiceName, 'Neural') ? 'female' : 'unknown',
            ];
        }

        $dynamic = $this->getEdgeTTSVoices();
        return !empty($dynamic) ? $dynamic : $voices;
    }

    public function isEdgeTTSAvailable(): bool {
        $result = $this->execCommand('which edge-tts 2>/dev/null');
        return !empty(trim($result));
    }

    public function clearCache(?string $language = null): int {
        try {
            $folder = $this->getCacheFolder();
            $files = $folder->getDirectoryListing();
            $removed = 0;
            foreach ($files as $file) {
                if ($language !== null && !str_starts_with($file->getName(), $language . '_')) {
                    continue;
                }
                $file->delete();
                $removed++;
            }
            return $removed;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to clear TTS cache: ' . $e->getMessage());
            return 0;
        }
    }

    private function edgeTTS(string $text, string $voice): string {
        $tmpFile = sys_get_temp_dir() . '/flashcards_tts_' . bin2hex(random_bytes(8)) . '.mp3';
        $safeText = escapeshellarg($text);
        $safeVoice = escapeshellarg($voice);
        $cmd = "edge-tts --text {$safeText} --voice {$safeVoice} --write-media {$tmpFile} 2>&1";
        $output = $this->execCommand($cmd, $exitCode);

        if ($exitCode !== 0) {
            @unlink($tmpFile);
            throw new \RuntimeException("edge-tts failed (exit code {$exitCode}): {$output}");
        }

        if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
            @unlink($tmpFile);
            throw new \RuntimeException('edge-tts produced empty output');
        }

        $data = file_get_contents($tmpFile);
        @unlink($tmpFile);
        return $data;
    }

    private function getEdgeTTSVoices(): array {
        if (!$this->isEdgeTTSAvailable()) return [];
        $output = $this->execCommand('edge-tts --list-voices 2>/dev/null', $exitCode);
        if ($exitCode !== 0 || empty($output)) return [];

        $voices = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^Name:\s+(\S+)/', trim($line), $m)) {
                $voiceName = $m[1];
                $langParts = explode('-', $voiceName);
                $lang = count($langParts) >= 2 ? "{$langParts[0]}-{$langParts[1]}" : $langParts[0];
                $voices[] = [
                    'id' => $voiceName,
                    'name' => $voiceName,
                    'language' => $lang,
                    'languageName' => $this->getLanguageName($langParts[0]),
                    'gender' => 'unknown',
                ];
            }
        }
        return $voices;
    }

    private function resolveVoice(string $language): string {
        if (isset(self::DEFAULT_VOICES[$language])) return self::DEFAULT_VOICES[$language];
        $short = substr($language, 0, 2);
        if (isset(self::DEFAULT_VOICES[$short])) return self::DEFAULT_VOICES[$short];
        return self::DEFAULT_VOICES['en-US'];
    }

    private function generateCacheId(string $text, string $voice): string {
        return hash('sha256', $voice . '|' . mb_strtolower($text));
    }

    private function hasCached(string $cacheId): bool {
        try {
            $this->getCacheFolder()->getFile($cacheId . '.mp3');
            return true;
        } catch (NotFoundException) {
            return false;
        }
    }

    private function storeInCache(string $cacheId, string $audioData): void {
        try {
            $folder = $this->getCacheFolder();
            $file = $folder->newFile($cacheId . '.mp3');
            $file->putContent($audioData);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to cache TTS audio: ' . $e->getMessage());
        }
    }

    private function getCacheFolder(): ISimpleFolder {
        try {
            return $this->appData->getFolder(self::CACHE_FOLDER);
        } catch (NotFoundException) {
            return $this->appData->newFolder(self::CACHE_FOLDER);
        }
    }

    private function execCommand(string $command, ?int &$exitCode = null): string {
        $output = [];
        exec($command, $output, $exitCode);
        return implode("\n", $output);
    }

    private function getLanguageName(string $code): string {
        return [
            'en' => 'English', 'ru' => 'Russian', 'de' => 'German',
            'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian',
            'pt' => 'Portuguese', 'ja' => 'Japanese', 'zh' => 'Chinese',
            'ko' => 'Korean', 'sr' => 'Serbian', 'hr' => 'Croatian',
            'uk' => 'Ukrainian', 'pl' => 'Polish', 'tr' => 'Turkish',
            'ar' => 'Arabic', 'hi' => 'Hindi',
        ][$code] ?? $code;
    }
}
