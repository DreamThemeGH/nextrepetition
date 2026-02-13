<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Review Controller
 *
 * Handles card review: accept rating → update SR via SM-2 → store in buffer.
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Service\BufferService;
use OCA\Flashcards\Service\SM2Service;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class ReviewController extends OCSController {

    public function __construct(
        IRequest $request,
        private BufferService $bufferService,
        private SM2Service $sm2Service,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Submit a review answer.
     *
     * Expected body:
     * {
     *   "path": "ObsidianSync/Serbian learning/Popular_word_387_flashcards.md",
     *   "cardIndex": 5,
     *   "rating": 2,       // 0=Again, 1=Hard, 2=Good, 3=Easy
     *   "srIndex": 0       // 0=front→back, 1=back→front (optional, default 0)
     * }
     */
    #[NoAdminRequired]
    public function answer(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $path = $this->request->getParam('path', '');
        $cardIndex = (int)$this->request->getParam('cardIndex', -1);
        $rating = (int)$this->request->getParam('rating', -1);
        $srIndex = (int)$this->request->getParam('srIndex', 0);

        // Validate
        if (empty($path)) {
            return new DataResponse(['error' => 'Path is required'], Http::STATUS_BAD_REQUEST);
        }
        if ($cardIndex < 0) {
            return new DataResponse(['error' => 'Valid cardIndex is required'], Http::STATUS_BAD_REQUEST);
        }
        if ($rating < 0 || $rating > 3) {
            return new DataResponse(['error' => 'Rating must be 0-3'], Http::STATUS_BAD_REQUEST);
        }

        // Get current card from buffer
        $cards = $this->bufferService->getCards($this->userId, $path);
        if ($cards === null) {
            return new DataResponse(['error' => 'Deck not open'], Http::STATUS_NOT_FOUND);
        }
        if (!isset($cards[$cardIndex])) {
            return new DataResponse(['error' => 'Card not found'], Http::STATUS_NOT_FOUND);
        }

        $card = $cards[$cardIndex];

        // Process review through SM-2
        $newSR = $this->sm2Service->processReview($card, $rating, $srIndex);

        // Update buffer
        $success = $this->bufferService->updateCardSR($this->userId, $path, $cardIndex, $newSR);
        if (!$success) {
            return new DataResponse(
                ['error' => 'Failed to update card SR data'],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        // Auto-save to file immediately to prevent data loss
        // (user may close browser tab at any time)
        $this->bufferService->save($this->userId, $path);

        return new DataResponse([
            'sr' => $newSR,
            'cardIndex' => $cardIndex,
        ]);
    }

    /**
     * Predict intervals for all ratings (for button labels).
     */
    #[NoAdminRequired]
    public function predict(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $path = $this->request->getParam('path', '');
        $cardIndex = (int)$this->request->getParam('cardIndex', -1);
        $srIndex = (int)$this->request->getParam('srIndex', 0);

        if (empty($path) || $cardIndex < 0) {
            return new DataResponse(['error' => 'Path and cardIndex are required'], Http::STATUS_BAD_REQUEST);
        }

        $cards = $this->bufferService->getCards($this->userId, $path);
        if ($cards === null || !isset($cards[$cardIndex])) {
            return new DataResponse(['error' => 'Card not found'], Http::STATUS_NOT_FOUND);
        }

        $predictions = $this->sm2Service->predictReview($cards[$cardIndex], $srIndex);

        return new DataResponse($predictions);
    }
}
