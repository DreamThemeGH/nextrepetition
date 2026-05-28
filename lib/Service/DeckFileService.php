<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Deck File Service
 *
 * Reads/writes .md files via Nextcloud Files API (IRootFolder).
 * A "deck" = a .md file that contains flashcards.
 */

namespace OCA\Flashcards\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class DeckFileService {

    private const SUPPORTED_EXTENSIONS = ['md', 'markdown'];
    private const MAX_SCAN_DEPTH = 5;

    public function __construct(
        private IRootFolder $rootFolder,
        private CardParserService $parser,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * List all .md files (decks) in the user's configured folder.
     *
     * @param string $userId
    * @param string $deckFolder Relative path within user's files (e.g. "/StudySync")
     * @return array[] Array of deck metadata
     */
    public function listDecks(string $userId, string $deckFolder): array {
        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $folder = $this->resolveFolder($userFolder, $deckFolder);
        } catch (NotFoundException) {
            $this->logger->warning("Deck folder not found: {$deckFolder} for user {$userId}");
            return [];
        }

        $decks = [];
        $this->scanFolder($folder, $userFolder->getPath(), $decks, 0);

        return $decks;
    }

    /**
     * List subfolders in the deck folder (for folder navigation).
     */
    public function listFolders(string $userId, string $deckFolder, string $subPath = ''): array {
        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
            $path = rtrim($deckFolder, '/');
            if ($subPath) {
                $path .= '/' . ltrim($subPath, '/');
            }
            $folder = $this->resolveFolder($userFolder, $path);
        } catch (NotFoundException) {
            return [];
        }

        $folders = [];
        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof Folder) {
                $folders[] = [
                    'name' => $node->getName(),
                    'path' => $this->relativePath($node->getPath(), $userFolder->getPath()),
                ];
            }
        }

        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $folders;
    }

    /**
     * Read a .md file content.
     *
     * @return string Raw file content
     * @throws NotFoundException
     */
    public function readFile(string $userId, string $filePath): string {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $file = $userFolder->get($filePath);

        if (!$file instanceof File) {
            throw new NotFoundException("Not a file: {$filePath}");
        }

        return $file->getContent();
    }

    /**
     * Write content back to a .md file.
     *
     * @throws NotFoundException
     */
    public function writeFile(string $userId, string $filePath, string $content): void {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $file = $userFolder->get($filePath);

        if (!$file instanceof File) {
            throw new NotFoundException("Not a file: {$filePath}");
        }

        $file->putContent($content);
    }

    /**
     * Create a new .md file.
     *
     * @return string Path to created file
     */
    public function createFile(string $userId, string $folderPath, string $fileName, string $content = ''): string {
        $userFolder = $this->rootFolder->getUserFolder($userId);

        try {
            $folder = $this->resolveFolder($userFolder, $folderPath);
        } catch (NotFoundException) {
            // Create folder if it doesn't exist
            $folder = $this->createFolderRecursive($userFolder, $folderPath);
        }

        if (!str_ends_with($fileName, '.md')) {
            $fileName .= '.md';
        }

        // Add deck tag if content is empty
        if (empty(trim($content))) {
            $deckName = pathinfo($fileName, PATHINFO_FILENAME);
            $content = "#flashcards/" . str_replace(' ', '_', $deckName) . "\n\n";
        }

        $file = $folder->newFile($fileName, $content);
        return $this->relativePath($file->getPath(), $userFolder->getPath());
    }

    /**
     * Delete a .md file.
     */
    public function deleteFile(string $userId, string $filePath): void {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $file = $userFolder->get($filePath);
        $file->delete();
    }

    /**
     * Get deck metadata with quick card/due counts.
     */
    public function getDeckMeta(string $userId, string $filePath): array {
        $content = $this->readFile($userId, $filePath);
        $counts = $this->parser->quickScan($content);

        $userFolder = $this->rootFolder->getUserFolder($userId);
        $file = $userFolder->get($filePath);

        return [
            'path' => $filePath,
            'name' => pathinfo($file->getName(), PATHINFO_FILENAME),
            'size' => $file->getSize(),
            'modified' => $file->getMTime(),
            'totalCards' => $counts['total'],
            'dueCards' => $counts['due'],
            'newCards' => $counts['new'],
        ];
    }

    // ========================================================================
    // Private helpers
    // ========================================================================

    /**
     * Recursively scan folder for .md files.
     */
    private function scanFolder(Folder $folder, string $userRootPath, array &$decks, int $depth): void {
        if ($depth > self::MAX_SCAN_DEPTH) {
            return;
        }

        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof File) {
                $ext = strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
                if (in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
                    $relativePath = $this->relativePath($node->getPath(), $userRootPath);

                    try {
                        $content = $node->getContent();
                        $counts = $this->parser->quickScan($content);
                    } catch (\Exception $e) {
                        $counts = ['total' => 0, 'due' => 0, 'new' => 0];
                    }

                    // Only include files that actually contain flashcards
                    if ($counts['total'] > 0) {
                        $decks[] = [
                            'path' => $relativePath,
                            'name' => pathinfo($node->getName(), PATHINFO_FILENAME),
                            'folder' => dirname($relativePath),
                            'size' => $node->getSize(),
                            'modified' => $node->getMTime(),
                            'totalCards' => $counts['total'],
                            'dueCards' => $counts['due'],
                            'newCards' => $counts['new'],
                        ];
                    }
                }
            } elseif ($node instanceof Folder) {
                $this->scanFolder($node, $userRootPath, $decks, $depth + 1);
            }
        }
    }

    /**
     * Resolve a relative path to a Folder object.
     */
    private function resolveFolder(Folder $userFolder, string $path): Folder {
        $path = trim($path, '/');
        if (empty($path)) {
            return $userFolder;
        }

        $node = $userFolder->get($path);
        if (!$node instanceof Folder) {
            throw new NotFoundException("Not a folder: {$path}");
        }
        return $node;
    }

    /**
     * Create folder tree recursively.
     */
    private function createFolderRecursive(Folder $root, string $path): Folder {
        $parts = explode('/', trim($path, '/'));
        $current = $root;

        foreach ($parts as $part) {
            if (empty($part)) continue;
            try {
                $node = $current->get($part);
                if ($node instanceof Folder) {
                    $current = $node;
                } else {
                    throw new \RuntimeException("Path component is not a folder: {$part}");
                }
            } catch (NotFoundException) {
                $current = $current->newFolder($part);
            }
        }

        return $current;
    }

    /**
     * Get path relative to user root.
     */
    private function relativePath(string $fullPath, string $rootPath): string {
        return ltrim(substr($fullPath, strlen($rootPath)), '/');
    }
}
