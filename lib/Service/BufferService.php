<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Buffer Service
 *
 * In-memory buffer for open decks. Holds parsed cards and tracks dirty state.
 * The buffer lives in the PHP session/request scope.
 *
 * Flow:
 *   1. Open deck → read .md → parse → store in ICache
 *   2. User answers card → update SR in cache → mark dirty
 *   3. Auto-save (frontend timer) or manual save → serialize → write .md
 *   4. Close deck → final save → clear from cache
 */

namespace OCA\Flashcards\Service;

use OCP\ICacheFactory;
use OCP\ICache;
use Psr\Log\LoggerInterface;

class BufferService {

    private const CACHE_PREFIX = 'flashcards_buffer_';
    private const CACHE_TTL = 86400; // 24 hours

    private ICache $cache;

    public function __construct(
        ICacheFactory $cacheFactory,
        private DeckFileService $fileService,
        private CardParserService $parser,
        private CardSerializerService $serializer,
        private LoggerInterface $logger,
    ) {
        $this->cache = $cacheFactory->createDistributed(self::CACHE_PREFIX);
    }

    /**
     * Open a deck: read file, parse, store in buffer.
     *
     * @return array Parse result with cards
     */
    public function openDeck(string $userId, string $filePath): array {
        $content = $this->fileService->readFile($userId, $filePath);
        $parseResult = $this->parser->parse($content, $filePath);

        $bufferKey = $this->bufferKey($userId, $filePath);
        $bufferData = [
            'userId' => $userId,
            'filePath' => $filePath,
            'parseResult' => $parseResult,
            'dirty' => false,
            'openedAt' => time(),
            'lastSaved' => time(),
        ];

        $this->cache->set($bufferKey, json_encode($bufferData), self::CACHE_TTL);

        return $parseResult;
    }

    /**
     * Get buffered deck data. Returns null if not open.
     */
    public function getBuffer(string $userId, string $filePath): ?array {
        $bufferKey = $this->bufferKey($userId, $filePath);
        $data = $this->cache->get($bufferKey);

        if ($data === null) {
            return null;
        }

        return json_decode($data, true);
    }

    /**
     * Get cards from an open deck buffer.
     */
    public function getCards(string $userId, string $filePath): ?array {
        $buffer = $this->getBuffer($userId, $filePath);
        return $buffer ? ($buffer['parseResult']['cards'] ?? []) : null;
    }

    /**
     * Get due cards from an open deck.
     * Each card gets 'dueDirections' array (e.g. [0], [1], or [0,1])
     * indicating which SR entry indices are due.
     */
    public function getDueCards(string $userId, string $filePath): array {
        $cards = $this->getCards($userId, $filePath);
        if ($cards === null) {
            // Open the deck first
            $parseResult = $this->openDeck($userId, $filePath);
            $cards = $parseResult['cards'];
        }

        $today = date('Y-m-d');
        $due = [];

        foreach ($cards as $card) {
            if ($card['state'] === 'new') {
                $card['dueDirections'] = [0]; // New cards start with front→back
                $due[] = $card;
                continue;
            }
            if ($card['state'] === 'due') {
                // Determine WHICH directions are actually due
                $dueDirections = [];
                if (isset($card['sr']) && is_array($card['sr'])) {
                    foreach ($card['sr'] as $idx => $sr) {
                        if (isset($sr['date']) && $sr['date'] !== '2000-01-01' && $sr['date'] <= $today) {
                            $dueDirections[] = $idx;
                        }
                    }
                }
                if (empty($dueDirections)) {
                    $dueDirections = [0];
                }
                $card['dueDirections'] = $dueDirections;
                $due[] = $card;
            }
        }

        return $due;
    }

    /**
     * Update a card's SR data in the buffer.
     */
    public function updateCardSR(string $userId, string $filePath, int $cardIndex, array $newSR): bool {
        $buffer = $this->getBuffer($userId, $filePath);
        if ($buffer === null) {
            return false;
        }

        if (!isset($buffer['parseResult']['cards'][$cardIndex])) {
            return false;
        }

        $buffer['parseResult']['cards'][$cardIndex]['sr'] = $newSR;

        // Update card state based on new SR data
        // A card is "due" only if any non-dummy SR entry has date <= today
        // After a review, all entries should be in the future → state = 'review'
        $today = date('Y-m-d');
        $isDue = false;
        $hasRealSR = false;
        foreach ($newSR as $sr) {
            if ($sr['date'] === '2000-01-01') {
                continue; // Dummy entry for unreviewed direction
            }
            $hasRealSR = true;
            if ($sr['date'] <= $today) {
                $isDue = true;
                break;
            }
        }

        if (!$hasRealSR) {
            $buffer['parseResult']['cards'][$cardIndex]['state'] = 'new';
        } else {
            $buffer['parseResult']['cards'][$cardIndex]['state'] = $isDue ? 'due' : 'review';
        }

        $buffer['dirty'] = true;

        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

        return true;
    }

    /**
     * Save buffer back to .md file if dirty.
     *
     * @return bool True if saved, false if clean or error
     */
    public function save(string $userId, string $filePath): bool {
        $buffer = $this->getBuffer($userId, $filePath);
        if ($buffer === null || !$buffer['dirty']) {
            $this->logger->debug('[SAVE] Skip: buffer null or not dirty', [
                'bufferNull' => $buffer === null,
                'dirty' => $buffer['dirty'] ?? false,
            ]);
            return false;
        }

        try {
            $content = $this->serializer->serialize(
                $buffer['parseResult'],
                $buffer['parseResult']['cards'],
            );

            // LOG: What we're writing
            $this->logger->debug('[SAVE] Writing to file', [
                'userId' => $userId,
                'filePath' => $filePath,
                'contentLength' => strlen($content),
                'cardCount' => count($buffer['parseResult']['cards']),
                'firstCard' => substr($buffer['parseResult']['cards'][0]['front'] ?? '', 0, 30),
                'firstSR' => $buffer['parseResult']['cards'][0]['sr'] ?? [],
            ]);

            $this->fileService->writeFile($userId, $filePath, $content);

            // LOG: Success
            $this->logger->debug('[SAVE] File written successfully', [
                'filePath' => $filePath,
            ]);

            // Update buffer state
            $buffer['dirty'] = false;
            $buffer['lastSaved'] = time();

            // Re-parse to get fresh line numbers
            $buffer['parseResult'] = $this->parser->parse($content, $filePath);

            $bufferKey = $this->bufferKey($userId, $filePath);
            $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save deck buffer: ' . $e->getMessage(), [
                'userId' => $userId,
                'filePath' => $filePath,
            ]);
            return false;
        }
    }

    /**
     * Close a deck: save if dirty, then remove from buffer.
     */
    public function closeDeck(string $userId, string $filePath): bool {
        $this->save($userId, $filePath);
        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->remove($bufferKey);
        return true;
    }

    /**
     * Check if a deck is open in the buffer.
     */
    public function isOpen(string $userId, string $filePath): bool {
        return $this->getBuffer($userId, $filePath) !== null;
    }

    /**
     * Check if buffer is dirty (has unsaved changes).
     */
    public function isDirty(string $userId, string $filePath): bool {
        $buffer = $this->getBuffer($userId, $filePath);
        return $buffer !== null && ($buffer['dirty'] ?? false);
    }

    /**
     * Add a new card to the buffered deck.
     */
    public function addCard(string $userId, string $filePath, array $cardData): ?array {
        $buffer = $this->getBuffer($userId, $filePath);
        if ($buffer === null) {
            return null;
        }

        // Build new card
        $newCard = array_merge($cardData, [
            'sr' => [],
            'srRaw' => '',
            'srLine' => -1,
            'state' => 'new',
            'context' => [],
            'index' => count($buffer['parseResult']['cards']),
        ]);

        $buffer['parseResult']['cards'][] = $newCard;

        // Also update the raw content via serializer
        $content = implode("\n", $buffer['parseResult']['rawLines']);
        $content = $this->serializer->addCard($content, $cardData);
        $buffer['parseResult']['rawLines'] = explode("\n", $content);

        $buffer['dirty'] = true;

        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

        return $newCard;
    }

    /**
     * Update a card's content in the buffer.
     */
    public function updateCard(string $userId, string $filePath, int $cardIndex, array $newData): bool {
        $buffer = $this->getBuffer($userId, $filePath);
        if ($buffer === null || !isset($buffer['parseResult']['cards'][$cardIndex])) {
            return false;
        }

        // Update in-memory card data
        $card = &$buffer['parseResult']['cards'][$cardIndex];
        foreach ($newData as $key => $value) {
            if ($key !== 'sr' && $key !== 'index' && $key !== 'line' && $key !== 'srLine') {
                $card[$key] = $value;
            }
        }

        // Update raw lines
        $content = $this->serializer->updateCard($buffer['parseResult'], $cardIndex, $newData);
        $buffer['parseResult']['rawLines'] = explode("\n", $content);

        $buffer['dirty'] = true;
        unset($card);

        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

        return true;
    }

    /**
     * Delete a card from the buffer.
     */
    public function deleteCard(string $userId, string $filePath, int $cardIndex): bool {
        $buffer = $this->getBuffer($userId, $filePath);
        if ($buffer === null || !isset($buffer['parseResult']['cards'][$cardIndex])) {
            return false;
        }

        // Remove from raw content
        $content = $this->serializer->removeCard($buffer['parseResult'], $cardIndex);
        $newParse = $this->parser->parse($content, $filePath);

        $buffer['parseResult'] = $newParse;
        $buffer['dirty'] = true;

        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

        return true;
    }

    public function resetProgress(string $userId, string $filePath): bool {
        $buffer = $this->getBuffer($userId, $filePath);

        if ($buffer === null) {
            $parseResult = $this->openDeck($userId, $filePath);
            $buffer = $this->getBuffer($userId, $filePath);
            if ($buffer === null) {
                $buffer = [
                    'userId' => $userId,
                    'filePath' => $filePath,
                    'parseResult' => $parseResult,
                    'dirty' => false,
                    'openedAt' => time(),
                    'lastSaved' => time(),
                ];
            }
        }

        $content = $this->serializer->clearSRMetadata($buffer['parseResult']);
        $buffer['parseResult'] = $this->parser->parse($content, $filePath);
        $buffer['dirty'] = true;

        $bufferKey = $this->bufferKey($userId, $filePath);
        $this->cache->set($bufferKey, json_encode($buffer), self::CACHE_TTL);

        return $this->save($userId, $filePath);
    }

    /**
     * Generate cache key for a user+file combination.
     */
    private function bufferKey(string $userId, string $filePath): string {
        return $userId . ':' . $filePath;
    }
}
