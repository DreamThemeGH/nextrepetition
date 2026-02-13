<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — SM-2 Service
 *
 * Wraps SM2Algorithm for use with the file-based SR metadata format.
 * Obsidian SR compatible: ease is stored as integer (250=2.5×),
 * interval in days, date as YYYY-MM-DD.
 *
 * No "repetitions" counter — Obsidian SR doesn't use it.
 * delayedBeforeReview is calculated from (today - dueDate).
 */

namespace OCA\Flashcards\Service;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use Psr\Log\LoggerInterface;

class SM2Service {

    public function __construct(
        private SM2Algorithm $algorithm,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process a review answer for a card.
     *
     * @param array $card Card data with current SR entries
     * @param int $rating User rating (0=Again, 1=Hard, 2=Good, 3=Easy)
     * @param int $srIndex Which SR entry to update (0=front→back, 1=back→front)
     * @return array Updated SR entries array
     */
    public function processReview(array $card, int $rating, int $srIndex = 0): array {
        $srEntries = $card['sr'] ?? [];
        $today = new \DateTime();
        $todayStr = $today->format('Y-m-d');

        // Get current SR data or use defaults for new cards
        if (isset($srEntries[$srIndex]) && $srEntries[$srIndex]['date'] !== '2000-01-01') {
            $current = $srEntries[$srIndex];
            $interval = $current['interval'];
            $ease = $current['ease'];

            // Calculate days overdue (delayedBeforeReview)
            $dueDate = new \DateTime($current['date']);
            $diff = $today->diff($dueDate);
            $delayDays = $diff->invert ? $diff->days : 0; // Only if today > dueDate
        } else {
            // New card
            $interval = SM2Algorithm::INITIAL_INTERVAL;
            $ease = SM2Algorithm::DEFAULT_EASE;
            $delayDays = 0;
        }

        // Calculate next review
        $result = $this->algorithm->calculateNextReview($rating, $interval, $ease, $delayDays);

        // Build new SR entry in file format
        $dueDate = clone $today;
        $dueDate->modify("+{$result['interval']} days");

        $newEntry = [
            'date' => $dueDate->format('Y-m-d'),
            'interval' => $result['interval'],
            'ease' => $result['ease'],
        ];

        // Ensure srEntries array is large enough (fill with dummy entries for unreviewed directions)
        while (count($srEntries) <= $srIndex) {
            $srEntries[] = [
                'date' => '2000-01-01',
                'interval' => SM2Algorithm::INITIAL_INTERVAL,
                'ease' => SM2Algorithm::DEFAULT_EASE,
            ];
        }

        $srEntries[$srIndex] = $newEntry;

        return $srEntries;
    }

    /**
     * Predict intervals for all possible ratings (for button labels).
     *
     * @return array<int, array{interval: int, ease: int, label: string, date: string}>
     */
    public function predictReview(array $card, int $srIndex = 0): array {
        $srEntries = $card['sr'] ?? [];
        $today = new \DateTime();

        if (isset($srEntries[$srIndex]) && $srEntries[$srIndex]['date'] !== '2000-01-01') {
            $current = $srEntries[$srIndex];
            $interval = $current['interval'];
            $ease = $current['ease'];

            $dueDate = new \DateTime($current['date']);
            $diff = $today->diff($dueDate);
            $delayDays = $diff->invert ? $diff->days : 0;
        } else {
            $interval = SM2Algorithm::INITIAL_INTERVAL;
            $ease = SM2Algorithm::DEFAULT_EASE;
            $delayDays = 0;
        }

        $predictions = $this->algorithm->predictIntervals($interval, $ease, $delayDays);

        // Add dates to predictions
        foreach ($predictions as $rating => &$pred) {
            $date = clone $today;
            $date->modify("+{$pred['interval']} days");
            $pred['date'] = $date->format('Y-m-d');
        }
        unset($pred);

        return $predictions;
    }
}
