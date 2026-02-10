<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Card Controller
 *
 * CRUD operations on cards within an open (buffered) deck.
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Service\BufferService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class CardController extends OCSController {

    public function __construct(
        IRequest $request,
        private BufferService $bufferService,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get all cards from an open deck.
     */
    #[NoAdminRequired]
    public function index(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $cards = $this->bufferService->getCards($this->userId, $path);
        if ($cards === null) {
            return new DataResponse(
                ['error' => 'Deck not open. Open it first.'],
                Http::STATUS_NOT_FOUND,
            );
        }

        return new DataResponse($cards);
    }

    /**
     * Get due cards from an open deck.
     */
    #[NoAdminRequired]
    public function due(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $due = $this->bufferService->getDueCards($this->userId, $path);
            return new DataResponse($due);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Add a new card to the buffered deck.
     */
    #[NoAdminRequired]
    public function create(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $type = $this->request->getParam('type', 'basic');
        $cardData = ['type' => $type];

        if ($type === 'basic') {
            $front = $this->request->getParam('front', '');
            $back = $this->request->getParam('back', '');

            if (empty(trim($front)) || empty(trim($back))) {
                return new DataResponse(
                    ['error' => 'Front and back are required'],
                    Http::STATUS_BAD_REQUEST,
                );
            }

            $cardData['front'] = trim($front);
            $cardData['back'] = trim($back);
            $cardData['transcription'] = trim($this->request->getParam('transcription', '') ?? '');
        } elseif ($type === 'cloze') {
            $sentence = $this->request->getParam('sentence', '');
            if (empty(trim($sentence))) {
                return new DataResponse(
                    ['error' => 'Sentence is required'],
                    Http::STATUS_BAD_REQUEST,
                );
            }
            $cardData['sentence'] = trim($sentence);
            $cardData['translation'] = trim($this->request->getParam('translation', '') ?? '');
        } else {
            return new DataResponse(['error' => 'Invalid card type'], Http::STATUS_BAD_REQUEST);
        }

        $result = $this->bufferService->addCard($this->userId, $path, $cardData);
        if ($result === null) {
            return new DataResponse(
                ['error' => 'Deck not open'],
                Http::STATUS_NOT_FOUND,
            );
        }

        return new DataResponse($result, Http::STATUS_CREATED);
    }

    /**
     * Update a card's content.
     */
    #[NoAdminRequired]
    public function update(string $path, int $index): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $newData = [];
        foreach (['front', 'back', 'transcription', 'sentence', 'translation'] as $field) {
            $val = $this->request->getParam($field);
            if ($val !== null) {
                $newData[$field] = trim($val);
            }
        }

        if (empty($newData)) {
            return new DataResponse(['error' => 'No data to update'], Http::STATUS_BAD_REQUEST);
        }

        $success = $this->bufferService->updateCard($this->userId, $path, $index, $newData);
        if (!$success) {
            return new DataResponse(
                ['error' => 'Card not found or deck not open'],
                Http::STATUS_NOT_FOUND,
            );
        }

        return new DataResponse(['updated' => true]);
    }

    /**
     * Delete a card.
     */
    #[NoAdminRequired]
    public function destroy(string $path, int $index): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $success = $this->bufferService->deleteCard($this->userId, $path, $index);
        if (!$success) {
            return new DataResponse(
                ['error' => 'Card not found or deck not open'],
                Http::STATUS_NOT_FOUND,
            );
        }

        return new DataResponse(['deleted' => true]);
    }
}
