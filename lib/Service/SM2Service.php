<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — SM-2 Service
 *
 * Wraps SM2Algorithm for use with the file-based SR metadata format.
 * Converts between SR tag format (date, interval, ease×100) and SM-2 parameters.
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
     * @param int $rating User rating (0=Again, 1=Hard, 2=Good, 3=Easy, 4=VeryEasy)
     * @param int $srIndex Which SR entry to update (0=front→back, 1=back→front)
     * @return array Updated SR entries array
     */
    public function processReview(array $card, int $rating, int $srIndex = 0): array {
        $srEntries = $card['sr'] ?? [];

        // Get current SR data or use defaults for new cards
        if (isset($srEntries[$srIndex])) {
            $current = $srEntries[$srIndex];
            $interval = $current['interval'];
            $ease = $current['ease'] / 100.0; // SR stores ease×100
            $repetitions = $this->estimateRepetitions($interval, $ease);
        } else {
            $interval = 0;
            $ease = SM2Algorithm::DEFAULT_EASE_FACTOR;
            $repetitions = 0;
        }

        // Calculate next review
        $result = $this->algorithm->calculateNextReview(
            $rating,
            $repetitions,
            $interval,
            $ease,
        );

        // Build new SR entry in file format
        $newEntry = [
            'date' => (new \DateTime())->modify("+{$result['interval']} days")->format('Y-m-d'),
            'interval' => $result['interval'],
            'ease' => (int)round($result['easeFactor'] * 100),
        ];

        // Ensure srEntries array is large enough
        while (count($srEntries) <= $srIndex) {
            $srEntries[] = [
                'date' => '2000-01-01',
                'interval' => 1,
                'ease' => (int)(SM2Algorithm::DEFAULT_EASE_FACTOR * 100),
            ];
        }

        $srEntries[$srIndex] = $newEntry;

        return $srEntries;
    }

    /**
     * Predict intervals for all possible ratings.
     *
     * @return array<int, array{interval: int, label: string, date: string}>
     */
    public function predictReview(array $card, int $srIndex = 0): array {
        $srEntries = $card['sr'] ?? [];

        if (isset($srEntries[$srIndex])) {
            $current = $srEntries[$srIndex];
            $interval = $current['interval'];
            $ease = $current['ease'] / 100.0;
            $repetitions = $this->estimateRepetitions($interval, $ease);
        } else {
            $interval = 0;
            $ease = SM2Algorithm::DEFAULT_EASE_FACTOR;
            $repetitions = 0;
        }

        $predictions = $this->algorithm->predictIntervals($repetitions, $interval, $ease);

        // Add dates to predictions
        $now = new \DateTime();
        foreach ($predictions as $rating => &$pred) {
            $date = clone $now;
            $date->modify("+{$pred['interval']} days");
            $pred['date'] = $date->format('Y-m-d');
        }
        unset($pred);

        return $predictions;
    }

    /**
     * Estimate repetitions count from interval and ease factor.
     * SM-2 doesn't store repetitions in file, so we reverse-engineer it.
     */
    private function estimateRepetitions(int $interval, float $ease): int {
        if ($interval <= 0) return 0;
        if ($interval <= 1) return 0;
        if ($interval <= 6) return 1;

        // Estimate from interval growth: interval ≈ ease^(repetitions-1) * 6
        $reps = 2;
        $expected = 6;
        while ($expected < $interval && $reps < 100) {
            $expected = (int)round($expected * $ease);
            $reps++;
        }

        return $reps;
    }
}
