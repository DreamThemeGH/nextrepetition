<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Stats Service
 *
 * Calculates statistics from SR metadata in .md files.
 * All data comes from files — no DB queries for stats.
 */

namespace OCA\Flashcards\Service;

use Psr\Log\LoggerInterface;

class StatsService {

    public function __construct(
        private DeckFileService $fileService,
        private CardParserService $parser,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Get overview statistics across all decks.
     */
    public function getOverview(string $userId, string $deckFolder): array {
        $decks = $this->fileService->listDecks($userId, $deckFolder);

        $totalCards             = 0;
        $totalDue               = 0;
        $totalNew               = 0;
        $totalReviewed          = 0;
        $totalReviewedToday     = 0;
        $totalReviewedLast2Weeks = 0;
        $deckStats              = [];

        foreach ($decks as $deck) {
            $totalCards              += $deck['totalCards'];
            $totalDue                += $deck['dueCards'];
            $totalNew                += $deck['newCards'];
            $totalReviewed           += ($deck['totalCards'] - $deck['newCards']);
            $totalReviewedToday      += ($deck['reviewedToday'] ?? 0);
            $totalReviewedLast2Weeks += ($deck['reviewedLast2Weeks'] ?? 0);

            $deckStats[] = [
                'name'          => $deck['name'],
                'path'          => $deck['path'],
                'total'         => $deck['totalCards'],
                'due'           => $deck['dueCards'],
                'new'           => $deck['newCards'],
                'reviewedToday' => $deck['reviewedToday'] ?? 0,
            ];
        }

        return [
            'totalDecks'            => count($decks),
            'totalCards'            => $totalCards,
            'totalDue'              => $totalDue,
            'totalNew'              => $totalNew,
            'totalReviewed'         => $totalReviewed,
            'reviewedToday'         => $totalReviewedToday,
            'reviewedLast2Weeks'    => $totalReviewedLast2Weeks,
            'decks'                 => $deckStats,
        ];
    }

    /**
     * Get detailed statistics for a single deck.
     */
    public function getDeckStats(string $userId, string $filePath): array {
        try {
            $content = $this->fileService->readFile($userId, $filePath);
        } catch (\Exception $e) {
            return ['error' => 'File not found'];
        }

        $parseResult = $this->parser->parse($content, $filePath);
        $cards = $parseResult['cards'];

        $states = ['new' => 0, 'due' => 0, 'review' => 0];
        $intervals = [];
        $easeFactors = [];
        $dueForecast = []; // days from now → count

        $today = new \DateTime();

        foreach ($cards as $card) {
            $states[$card['state']] = ($states[$card['state']] ?? 0) + 1;

            // Find earliest due date for this card (for forecast)
            $earliestDue = null;
            
            foreach ($card['sr'] as $sr) {
                $intervals[] = $sr['interval'];
                $easeFactors[] = $sr['ease'] / 100.0;

                $dueDate = new \DateTime($sr['date']);
                if ($earliestDue === null || $dueDate < $earliestDue) {
                    $earliestDue = $dueDate;
                }
            }

            // Count card once for due forecast based on earliest due date
            if ($earliestDue !== null) {
                $diff = (int)$today->diff($earliestDue)->format('%r%a');
                if ($diff >= 0 && $diff <= 30) {
                    $dueForecast[$diff] = ($dueForecast[$diff] ?? 0) + 1;
                }
            }
        }

        // Fill forecast gaps
        for ($d = 0; $d <= 30; $d++) {
            if (!isset($dueForecast[$d])) {
                $dueForecast[$d] = 0;
            }
        }
        ksort($dueForecast);

        return [
            'name' => pathinfo($filePath, PATHINFO_FILENAME),
            'path' => $filePath,
            'totalCards' => count($cards),
            'states' => $states,
            'conflicts' => count($parseResult['conflicts']),
            'averageInterval' => count($intervals) > 0 ? round(array_sum($intervals) / count($intervals), 1) : 0,
            'averageEase' => count($easeFactors) > 0 ? round(array_sum($easeFactors) / count($easeFactors), 2) : 2.5,
            'maxInterval' => count($intervals) > 0 ? max($intervals) : 0,
            'dueForecast' => $dueForecast,
            'intervalDistribution' => $this->buildIntervalDistribution($intervals),
        ];
    }

    /**
     * Get aggregated statistics for top-N (or all) decks.
     *
     * Decks are ranked by activity (dueCards + newCards desc), then by totalCards.
     * Returns combined due forecast and interval distribution for the top slice
     * AND for all decks — so the frontend can render dual comparison charts.
     *
     * @param int $topN How many decks to include in the "top" slice (use 9999 for all)
     */
    public function getAggregatedStats(string $userId, string $deckFolder, int $topN): array {
        $decks = $this->fileService->listDecks($userId, $deckFolder);

        // Sort by activity (due + new) descending, then by total cards
        usort($decks, static function (array $a, array $b): int {
            $actA = $a['dueCards'] + $a['newCards'];
            $actB = $b['dueCards'] + $b['newCards'];
            return $actA !== $actB ? $actB - $actA : $b['totalCards'] - $a['totalCards'];
        });

        $topDecks  = array_slice($decks, 0, $topN);
        $topPaths  = array_column($topDecks, 'path');

        // Zero-fill 0–30 day forecast buckets
        $emptyForecast = [];
        for ($d = 0; $d <= 30; $d++) {
            $emptyForecast[$d] = 0;
        }
        $emptyDist = [
            '0-1d' => 0, '2-7d' => 0, '1-2w' => 0, '2w-1m' => 0,
            '1-3m' => 0, '3-6m' => 0, '6m-1y' => 0, '1y+' => 0,
        ];

        $topForecast = $emptyForecast;
        $allForecast = $emptyForecast;
        $topDist     = $emptyDist;
        $allDist     = $emptyDist;

        $topSummary = ['totalCards' => 0, 'totalDue' => 0, 'totalNew' => 0, 'totalReviewed' => 0];
        $allSummary = ['totalCards' => 0, 'totalDue' => 0, 'totalNew' => 0, 'totalReviewed' => 0];

        foreach ($decks as $deck) {
            $allSummary['totalCards']    += $deck['totalCards'];
            $allSummary['totalDue']      += $deck['dueCards'];
            $allSummary['totalNew']      += $deck['newCards'];
            $allSummary['totalReviewed'] += $deck['totalCards'] - $deck['newCards'];

            $isTop = in_array($deck['path'], $topPaths, true);
            if ($isTop) {
                $topSummary['totalCards']    += $deck['totalCards'];
                $topSummary['totalDue']      += $deck['dueCards'];
                $topSummary['totalNew']      += $deck['newCards'];
                $topSummary['totalReviewed'] += $deck['totalCards'] - $deck['newCards'];
            }

            try {
                $deckStats = $this->getDeckStats($userId, $deck['path']);
            } catch (\Exception) {
                continue;
            }

            foreach ($deckStats['dueForecast'] as $day => $count) {
                $allForecast[$day] = ($allForecast[$day] ?? 0) + $count;
                if ($isTop) {
                    $topForecast[$day] = ($topForecast[$day] ?? 0) + $count;
                }
            }

            foreach ($deckStats['intervalDistribution'] as $bucket => $count) {
                $allDist[$bucket] = ($allDist[$bucket] ?? 0) + $count;
                if ($isTop) {
                    $topDist[$bucket] = ($topDist[$bucket] ?? 0) + $count;
                }
            }
        }

        $makeMeta = static fn(array $d): array => [
            'name'  => $d['name'],
            'path'  => $d['path'],
            'total' => $d['totalCards'],
            'due'   => $d['dueCards'],
            'new'   => $d['newCards'],
        ];

        return [
            'topN'                    => min($topN, count($decks)),
            'totalDecks'              => count($decks),
            'topDecks'                => array_map($makeMeta, $topDecks),
            'allDecks'                => array_map($makeMeta, $decks),
            'topSummary'              => $topSummary,
            'allSummary'              => $allSummary,
            'topDueForecast'          => $topForecast,
            'allDueForecast'          => $allForecast,
            'topIntervalDistribution' => $topDist,
            'allIntervalDistribution' => $allDist,
        ];
    }

    /**
     * Get due counts per deck (for sidebar/dashboard).
     */
    public function getDueCounts(string $userId, string $deckFolder): array {
        $decks = $this->fileService->listDecks($userId, $deckFolder);

        $counts = [];
        foreach ($decks as $deck) {
            $counts[] = [
                'name' => $deck['name'],
                'path' => $deck['path'],
                'due' => $deck['dueCards'],
                'new' => $deck['newCards'],
                'total' => $deck['totalCards'],
            ];
        }

        return $counts;
    }

    /**
     * Build interval distribution for histogram.
     */
    private function buildIntervalDistribution(array $intervals): array {
        $buckets = [
            '0-1d' => 0,
            '2-7d' => 0,
            '1-2w' => 0,
            '2w-1m' => 0,
            '1-3m' => 0,
            '3-6m' => 0,
            '6m-1y' => 0,
            '1y+' => 0,
        ];

        foreach ($intervals as $i) {
            if ($i <= 1) $buckets['0-1d']++;
            elseif ($i <= 7) $buckets['2-7d']++;
            elseif ($i <= 14) $buckets['1-2w']++;
            elseif ($i <= 30) $buckets['2w-1m']++;
            elseif ($i <= 90) $buckets['1-3m']++;
            elseif ($i <= 180) $buckets['3-6m']++;
            elseif ($i <= 365) $buckets['6m-1y']++;
            else $buckets['1y+']++;
        }

        return $buckets;
    }
}
