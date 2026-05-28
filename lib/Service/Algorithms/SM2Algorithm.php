<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Spaced Repetition Algorithm
 *
 * Compatible with Markdown spaced-repetition files using the same schedule fields.
 * Uses the same interval/ease calculations to ensure .md file interoperability.
 *
 * Markdown spaced-repetition scheduling algorithm:
 *   Easy:  ease += 20; interval = (interval + delayDays) * ease / 100 * easyBonus
 *   Good:  interval = (interval + delayDays/2) * ease / 100
 *   Hard:  ease = max(130, ease - 20); interval = max(1, (interval + delayDays/4) * lapsesIntervalChange)
 *   Again: reset to initial interval (1 day), ease = max(130, ease - 20)
 *
 * Key difference from classic SM-2: no "repetitions" counter needed.
 * Ease is stored as integer (250 = 2.5× multiplier).
 */

namespace OCA\Flashcards\Service\Algorithms;

class SM2Algorithm {
    // Rating constants (match frontend: 0=Again, 1=Hard, 2=Good, 3=Easy)
    public const RATING_AGAIN = 0;
    public const RATING_HARD = 1;
    public const RATING_GOOD = 2;
    public const RATING_EASY = 3;

    // Algorithm constants (match the file format defaults)
    public const DEFAULT_EASE = 250;       // 2.5× multiplier (stored as integer)
    public const MIN_EASE = 130;           // 1.3× minimum (stored as integer)
    public const INITIAL_INTERVAL = 1;     // 1 day for new cards
    public const MAXIMUM_INTERVAL = 36525; // ~100 years cap
    public const EASY_BONUS = 1.3;         // Extra multiplier for Easy
    public const LAPSES_INTERVAL_CHANGE = 0.5; // Interval reduction for Hard

    /**
    * Calculate the next review schedule.
     *
     * @param int   $rating   User rating: 0=Again, 1=Hard, 2=Good, 3=Easy
     * @param int   $interval Current interval in days (from SR tag)
     * @param int   $ease     Current ease as integer (250=2.5×, from SR tag)
     * @param int   $delayDays Days overdue (today - dueDate, 0 if not overdue)
     * @return array{interval: int, ease: int}
     */
    public function calculateNextReview(
        int $rating,
        int $interval,
        int $ease,
        int $delayDays = 0,
    ): array {
        $delayDays = max(0, $delayDays);
        $newInterval = (float)$interval;

        switch ($rating) {
            case self::RATING_EASY:
                $ease += 20;
                $newInterval = (($interval + $delayDays) * $ease) / 100.0;
                $newInterval *= self::EASY_BONUS;
                break;

            case self::RATING_GOOD:
                $newInterval = (($interval + $delayDays / 2) * $ease) / 100.0;
                break;

            case self::RATING_HARD:
                $ease = max(self::MIN_EASE, $ease - 20);
                $newInterval = max(1, ($interval + $delayDays / 4) * self::LAPSES_INTERVAL_CHANGE);
                break;

            case self::RATING_AGAIN:
            default:
                $ease = max(self::MIN_EASE, $ease - 20);
                $newInterval = self::INITIAL_INTERVAL;
                break;
        }

        // Apply maximum interval cap
        $newInterval = min($newInterval, self::MAXIMUM_INTERVAL);

        // Round to integer (the file format stores whole days in this app)
        $newInterval = max(1, (int)round($newInterval));

        return [
            'interval' => $newInterval,
            'ease' => $ease,
        ];
    }

    /**
     * Predict intervals for all possible ratings (for button labels).
     *
     * @param int $interval  Current interval in days
     * @param int $ease      Current ease as integer (250=2.5×)
     * @param int $delayDays Days overdue
     * @return array<int, array{interval: int, label: string}>
     */
    public function predictIntervals(int $interval, int $ease, int $delayDays = 0): array {
        $predictions = [];
        for ($rating = 0; $rating <= 3; $rating++) {
            $result = $this->calculateNextReview($rating, $interval, $ease, $delayDays);
            $predictions[$rating] = [
                'interval' => $result['interval'],
                'ease' => $result['ease'],
                'label' => self::formatInterval($result['interval']),
            ];
        }
        return $predictions;
    }

    /**
     * Format interval in days to human-readable string.
     */
    public static function formatInterval(int $days): string {
        if ($days < 1) return '< 1d';
        if ($days === 1) return '1d';
        if ($days < 30) return $days . 'd';
        if ($days < 365) return round($days / 30, 1) . 'mo';
        return round($days / 365, 1) . 'y';
    }

    public function isValidRating(int $rating): bool {
        return $rating >= self::RATING_AGAIN && $rating <= self::RATING_EASY;
    }
}
