<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Deck Controller
 *
 * Manages decks (= .md files). Lists, opens, saves, creates, deletes.
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Db\UserSettingsMapper;
use OCA\Flashcards\Service\BufferService;
use OCA\Flashcards\Service\ConflictResolver;
use OCA\Flashcards\Service\DeckFileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class DeckController extends OCSController {

    public function __construct(
        IRequest $request,
        private DeckFileService $fileService,
        private BufferService $bufferService,
        private ConflictResolver $conflictResolver,
        private UserSettingsMapper $settingsMapper,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * List all decks (= .md files with flashcards).
     */
    #[NoAdminRequired]
    public function index(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $settings = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');

        try {
            $decks = $this->fileService->listDecks($this->userId, $deckFolder);
            return new DataResponse($decks);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to list decks: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Open a deck: read, resolve conflicts, parse, buffer.
     *
     * @param string $path File path relative to user's files
     */
    #[NoAdminRequired]
    public function open(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            // Read file and resolve conflicts if any
            $content = $this->fileService->readFile($this->userId, $path);
            $conflictsResolved = 0;

            if ($this->conflictResolver->hasConflicts($content)) {
                $result = $this->conflictResolver->resolve($content, 'auto');
                $content = $result['content'];
                $conflictsResolved = $result['resolved'];

                // Write resolved content back
                if ($conflictsResolved > 0) {
                    $this->fileService->writeFile($this->userId, $path, $content);
                }
            }

            // Open in buffer
            $parseResult = $this->bufferService->openDeck($this->userId, $path);

            return new DataResponse([
                'cards' => $parseResult['cards'],
                'tag' => $parseResult['tag'],
                'totalCards' => count($parseResult['cards']),
                'conflictsResolved' => $conflictsResolved,
            ]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to open deck: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Save the current buffer state back to the .md file.
     */
    #[NoAdminRequired]
    public function save(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $saved = $this->bufferService->save($this->userId, $path);
            return new DataResponse(['saved' => $saved]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to save: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Close an open deck (saves if dirty).
     */
    #[NoAdminRequired]
    public function close(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $this->bufferService->closeDeck($this->userId, $path);
        return new DataResponse(['closed' => true]);
    }

    /**
     * Create a new .md deck file.
     */
    #[NoAdminRequired]
    public function create(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $name = $this->request->getParam('name', '');
        $folder = $this->request->getParam('folder', '');

        if (empty(trim($name))) {
            return new DataResponse(['error' => 'Deck name is required'], Http::STATUS_BAD_REQUEST);
        }

        $settings = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');
        $targetFolder = $deckFolder;
        if (!empty($folder)) {
            $targetFolder = rtrim($deckFolder, '/') . '/' . ltrim($folder, '/');
        }

        try {
            $path = $this->fileService->createFile($this->userId, $targetFolder, $name);
            return new DataResponse(['path' => $path], Http::STATUS_CREATED);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to create deck: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Delete a deck (.md file).
     */
    #[NoAdminRequired]
    public function destroy(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            // Close buffer if open
            if ($this->bufferService->isOpen($this->userId, $path)) {
                $this->bufferService->closeDeck($this->userId, $path);
            }

            $this->fileService->deleteFile($this->userId, $path);
            return new DataResponse(['deleted' => true]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to delete deck: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * List subfolders in the deck folder.
     */
    #[NoAdminRequired]
    public function folders(string $subPath = ''): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $settings = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');

        $folders = $this->fileService->listFolders($this->userId, $deckFolder, $subPath);
        return new DataResponse($folders);
    }

    #[NoAdminRequired]
    public function resetProgress(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $reset = $this->bufferService->resetProgress($this->userId, $path);
            return new DataResponse(['reset' => $reset]);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => 'Failed to reset progress: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
