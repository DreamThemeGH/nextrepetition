<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — SM-2 Spaced Repetition Algorithm
 * (Reused from v1 with minimal changes)
 */

namespace OCA\Flashcards\Service\Algorithms;

class SM2Algorithm {
    public const RATING_AGAIN = 0;
    public const RATING_HARD = 1;
    public const RATING_GOOD = 2;
    public const RATING_EASY = 3;
    public const RATING_VERY_EASY = 4;

    public const MIN_EASE_FACTOR = 1.3;
    public const DEFAULT_EASE_FACTOR = 2.5;

    /**
     * Calculate the next review parameters based on the user's rating.
     *
     * @param int $rating User's self-assessment (0-4)
     * @param int $repetitions Number of consecutive correct reviews
     * @param int $interval Current interval in days
     * @param float $easeFactor Current ease factor (≥ 1.3)
     * @return array{interval: int, repetitions: int, easeFactor: float, state: string}
     */
    public function calculateNextReview(
        int $rating,
        int $repetitions,
        int $interval,
        float $easeFactor,
    ): array {
        $rating = max(0, min(4, $rating));

        if ($rating >= self::RATING_GOOD) {
            if ($repetitions === 0) {
                $interval = 1;
            } elseif ($repetitions === 1) {
                $interval = 6;
            } else {
                $interval = (int)round($interval * $easeFactor);
            }
            $repetitions++;
            $state = $repetitions >= 2 ? 'review' : 'learning';
        } else {
            $repetitions = 0;
            $interval = 1;
            $state = 'relearning';
        }

        // SM-2 ease factor update
        $easeFactor = $easeFactor + (0.1 - (4 - $rating) * (0.08 + (4 - $rating) * 0.02));
        if ($easeFactor < self::MIN_EASE_FACTOR) {
            $easeFactor = self::MIN_EASE_FACTOR;
        }

        return [
            'interval' => $interval,
            'repetitions' => $repetitions,
            'easeFactor' => round($easeFactor, 2),
            'state' => $state,
        ];
    }

    /**
     * Predict intervals for all possible ratings (for button labels).
     *
     * @return array<int, array{interval: int, label: string}>
     */
    public function predictIntervals(int $repetitions, int $interval, float $easeFactor): array {
        $predictions = [];
        for ($rating = 0; $rating <= 4; $rating++) {
            $result = $this->calculateNextReview($rating, $repetitions, $interval, $easeFactor);
            $predictions[$rating] = [
                'interval' => $result['interval'],
                'label' => $this->formatInterval($result['interval']),
            ];
        }
        return $predictions;
    }

    /**
     * Format interval in days to human-readable string.
     */
    public function formatInterval(int $days): string {
        if ($days < 1) return '< 1d';
        if ($days === 1) return '1d';
        if ($days < 30) return $days . 'd';
        if ($days < 365) return round($days / 30, 1) . 'mo';
        return round($days / 365, 1) . 'y';
    }

    public function isValidRating(int $rating): bool {
        return $rating >= self::RATING_AGAIN && $rating <= self::RATING_VERY_EASY;
    }
}
