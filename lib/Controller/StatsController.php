<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Stats Controller
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Db\UserSettingsMapper;
use OCA\Flashcards\Service\StatsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class StatsController extends OCSController {

    public function __construct(
        IRequest $request,
        private StatsService $statsService,
        private UserSettingsMapper $settingsMapper,
        private ?string $userId,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get overview statistics across all decks.
     */
    #[NoAdminRequired]
    public function overview(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $settings = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');

        try {
            $stats = $this->statsService->getOverview($this->userId, $deckFolder);
            return new DataResponse($stats);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Get detailed statistics for a single deck.
     */
    #[NoAdminRequired]
    public function deck(string $path): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $stats = $this->statsService->getDeckStats($this->userId, $path);
            return new DataResponse($stats);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Get due counts per deck.
     */
    #[NoAdminRequired]
    public function dueCounts(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $settings = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');

        try {
            $counts = $this->statsService->getDueCounts($this->userId, $deckFolder);
            return new DataResponse($counts);
        } catch (\Exception $e) {
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }

    /**
     * Get aggregated stats for top-N (or all) decks.
     * Used by the Statistics page for combined forecast + distribution charts.
     *
     * @param string|null $topn Number of top decks (by activity). 9999 = all.
     */
    #[NoAdminRequired]
    public function aggregated(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $settings   = $this->settingsMapper->getOrCreate($this->userId);
        $deckFolder = $settings->getSetting('deckFolder');

        $rawTopN = $this->request->getParam('topn', $this->request->getParam('topN', '3'));
        $parsedTopN = (int) $rawTopN;
        if ($parsedTopN <= 0) {
            $parsedTopN = 3;
        }
        $parsedTopN = max(1, min(9999, $parsedTopN));

        $this->logger->debug('[STATS] aggregated request received', [
            'userId' => $this->userId,
            'rawTopN' => $rawTopN,
            'parsedTopN' => $parsedTopN,
            'deckFolder' => $deckFolder,
        ]);

        try {
            $stats = $this->statsService->getAggregatedStats($this->userId, $deckFolder, $parsedTopN);
            $this->logger->debug('[STATS] aggregated response ready', [
                'userId' => $this->userId,
                'topN' => $parsedTopN,
                'topDeckCount' => count($stats['topDecks'] ?? []),
                'allDeckCount' => count($stats['allDecks'] ?? []),
            ]);
            return new DataResponse($stats);
        } catch (\Exception $e) {
            $this->logger->error('[STATS] aggregated failed: ' . $e->getMessage(), [
                'userId' => $this->userId,
                'topN' => $parsedTopN,
                'exception' => $e,
            ]);
            return new DataResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
