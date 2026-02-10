<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — TTS Controller (reused from v1)
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Service\TTSService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\ICacheFactory;
use OCP\IRequest;

class TTSController extends OCSController {

    private const RATE_LIMIT = 30;
    private const RATE_WINDOW = 60;

    public function __construct(
        IRequest $request,
        private TTSService $ttsService,
        private ICacheFactory $cacheFactory,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function synthesize(): DataResponse {
        if ($this->userId !== null) {
            $cache = $this->cacheFactory->createDistributed('flashcards_tts');
            $key = 'rate_' . $this->userId;
            $count = (int)($cache->get($key) ?? 0);
            if ($count >= self::RATE_LIMIT) {
                return new DataResponse(
                    ['error' => 'Rate limit exceeded. Try again later.'],
                    Http::STATUS_TOO_MANY_REQUESTS,
                );
            }
            $cache->set($key, $count + 1, self::RATE_WINDOW);
        }

        $text = $this->request->getParam('text', '');
        $language = $this->request->getParam('language', 'en-US');
        $voice = $this->request->getParam('voice');

        if (empty(trim($text))) {
            return new DataResponse(['error' => 'Text is required'], Http::STATUS_BAD_REQUEST);
        }

        if (mb_strlen($text) > 500) {
            return new DataResponse(['error' => 'Text too long (max 500 characters)'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $result = $this->ttsService->synthesize($text, (string)$language, $voice ? (string)$voice : null);
            return new DataResponse($result, Http::STATUS_OK);
        } catch (\RuntimeException $e) {
            return new DataResponse(
                ['error' => 'TTS synthesis failed: ' . $e->getMessage()],
                Http::STATUS_SERVICE_UNAVAILABLE,
            );
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function audio(string $id): DataDownloadResponse|DataResponse {
        if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
            return new DataResponse(['error' => 'Invalid audio ID'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $data = $this->ttsService->getAudio($id);
            return new DataDownloadResponse($data, $id . '.mp3', 'audio/mpeg');
        } catch (\OCP\Files\NotFoundException) {
            return new DataResponse(['error' => 'Audio not found'], Http::STATUS_NOT_FOUND);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function voices(): DataResponse {
        try {
            $voices = $this->ttsService->getVoices();
            $available = $this->ttsService->isEdgeTTSAvailable();
            return new DataResponse([
                'available' => $available,
                'engine' => $available ? 'edge-tts' : 'browser',
                'voices' => $voices,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
