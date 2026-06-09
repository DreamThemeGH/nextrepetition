<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — SM-2 Algorithm Unit Tests
 *
 * Verifies the Obsidian-compatible spaced repetition algorithm against
 * expected values from obsidian-spaced-repetition scheduling.test.ts.
 *
 * Formula reference (matching Obsidian OSR):
 *   Easy:  ease += 20; interval = (interval + delayDays) * ease / 100 * 1.3
 *   Good:  interval = (interval + delayDays/2) * ease / 100
 *   Hard:  ease = max(130, ease - 20); interval = max(1, (interval + delayDays/4) * 0.5)
 *   Again: ease = max(130, ease - 20); interval = 1
 *
 * Constants: DEFAULT_EASE=250, MIN_EASE=130, INITIAL_INTERVAL=1, MAX_INTERVAL=36525
 */

namespace OCA\Flashcards\Tests\Unit\Service\Algorithms;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use PHPUnit\Framework\TestCase;

class SM2AlgorithmTest extends TestCase {
    private SM2Algorithm $sm2;

    protected function setUp(): void {
        $this->sm2 = new SM2Algorithm();
    }

    // ========================================================================
    // Rating constants
    // ========================================================================

    public function testRatingConstants(): void {
        $this->assertEquals(0, SM2Algorithm::RATING_AGAIN);
        $this->assertEquals(1, SM2Algorithm::RATING_HARD);
        $this->assertEquals(2, SM2Algorithm::RATING_GOOD);
        $this->assertEquals(3, SM2Algorithm::RATING_EASY);
    }

    public function testDefaultConstants(): void {
        $this->assertEquals(250, SM2Algorithm::DEFAULT_EASE);
        $this->assertEquals(130, SM2Algorithm::MIN_EASE);
        $this->assertEquals(1, SM2Algorithm::INITIAL_INTERVAL);
        $this->assertEquals(36525, SM2Algorithm::MAXIMUM_INTERVAL);
        $this->assertEquals(1.3, SM2Algorithm::EASY_BONUS);
        $this->assertEquals(0.5, SM2Algorithm::LAPSES_INTERVAL_CHANGE);
    }

    // ========================================================================
    // New card reviews (interval=1, ease=250, delay=0)
    // Values verified against Obsidian scheduling.test.ts
    // ========================================================================

    public function testNewCardEasy(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            SM2Algorithm::INITIAL_INTERVAL,
            SM2Algorithm::DEFAULT_EASE,
            0
        );
        // (1+0)*270/100*1.3 = 3.51 → round → 4
        $this->assertEquals(270, $result['ease']);
        $this->assertEquals(4, $result['interval']);
    }

    public function testNewCardGood(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::INITIAL_INTERVAL,
            SM2Algorithm::DEFAULT_EASE,
            0
        );
        // (1+0)*250/100 = 2.5 → round → 3
        $this->assertEquals(250, $result['ease']);
        $this->assertEquals(3, $result['interval']);
    }

    public function testNewCardHard(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_HARD,
            SM2Algorithm::INITIAL_INTERVAL,
            SM2Algorithm::DEFAULT_EASE,
            0
        );
        // ease = max(130, 250-20) = 230; interval = max(1, (1+0)*0.5) = 1
        $this->assertEquals(230, $result['ease']);
        $this->assertEquals(1, $result['interval']);
    }

    public function testNewCardAgain(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            SM2Algorithm::INITIAL_INTERVAL,
            SM2Algorithm::DEFAULT_EASE,
            0
        );
        // ease = max(130, 250-20) = 230; interval = 1 (reset)
        $this->assertEquals(230, $result['ease']);
        $this->assertEquals(1, $result['interval']);
    }

    // ========================================================================
    // Overdue card reviews (interval=10, ease=250, delay=2)
    // Values verified against Obsidian scheduling.test.ts
    // ========================================================================

    public function testOverdueEasy(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            10,
            250,
            2
        );
        // (10+2)*270/100*1.3 = 42.12 → round → 42
        $this->assertEquals(270, $result['ease']);
        $this->assertEquals(42, $result['interval']);
    }

    public function testOverdueGood(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            10,
            250,
            2
        );
        // (10+1)*250/100 = 27.5 → round → 28
        $this->assertEquals(250, $result['ease']);
        $this->assertEquals(28, $result['interval']);
    }

    public function testOverdueHard(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_HARD,
            10,
            250,
            2
        );
        // ease = max(130, 250-20) = 230
        // interval = max(1, (10 + 2/4) * 0.5) = max(1, 5.25) = 5.25 → round → 5
        // Verified against Obsidian: {ease: 230, interval: 5}
        $this->assertEquals(230, $result['ease']);
        $this->assertEquals(5, $result['interval']);
    }

    // ========================================================================
    // Already-reviewed card (non-overdue)
    // ========================================================================

    public function testReviewedCardEasy(): void {
        // Card reviewed yesterday, interval=3, ease=250
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            3,
            250,
            0
        );
        // (3+0)*270/100*1.3 = 10.53 → round → 11
        $this->assertEquals(270, $result['ease']);
        $this->assertEquals(11, $result['interval']);
    }

    public function testReviewedCardGood(): void {
        // Card reviewed yesterday, interval=3, ease=250
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            3,
            250,
            0
        );
        // (3+0)*250/100 = 7.5 → round → 8
        $this->assertEquals(250, $result['ease']);
        $this->assertEquals(8, $result['interval']);
    }

    public function testReviewedCardHard(): void {
        // Card reviewed yesterday, interval=8, ease=250
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_HARD,
            8,
            250,
            0
        );
        // ease = 230; interval = max(1, (8+0)*0.5) = max(1, 4) = 4
        $this->assertEquals(230, $result['ease']);
        $this->assertEquals(4, $result['interval']);
    }

    public function testReviewedCardAgain(): void {
        // Card with interval=8, ease=250 — fail resets to 1
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            8,
            250,
            0
        );
        $this->assertEquals(230, $result['ease']);
        $this->assertEquals(1, $result['interval']);
    }

    // ========================================================================
    // Ease boundary tests
    // ========================================================================

    public function testEaseNeverBelowMinimum(): void {
        // Card at minimum ease, rate Again
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            1,
            SM2Algorithm::MIN_EASE,
            0
        );
        $this->assertEquals(SM2Algorithm::MIN_EASE, $result['ease']);
        $this->assertEquals(1, $result['interval']);
    }

    public function testEaseNeverBelowMinimumHard(): void {
        // Card at minimum ease, rate Hard
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_HARD,
            1,
            SM2Algorithm::MIN_EASE,
            0
        );
        $this->assertEquals(SM2Algorithm::MIN_EASE, $result['ease']);
    }

    public function testEaseNeverBelowMinimumAgain(): void {
        // Card at ease=140, rate Again → should floor at 130
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            5,
            140,
            0
        );
        $this->assertEquals(SM2Algorithm::MIN_EASE, $result['ease']);
    }

    public function testEaseAccumulatesWithEasy(): void {
        // Multiple Easy ratings should keep increasing ease
        $ease = 250;
        $interval = 1;
        for ($i = 0; $i < 5; $i++) {
            $result = $this->sm2->calculateNextReview(
                SM2Algorithm::RATING_EASY,
                $interval,
                $ease,
                0
            );
            $ease = $result['ease'];
            $interval = $result['interval'];
            $this->assertEquals(250 + ($i + 1) * 20, $ease);
        }
    }

    // ========================================================================
    // Maximum interval cap
    // ========================================================================

    public function testMaximumIntervalCap(): void {
        // Huge interval + Easy → should cap at MAXIMUM_INTERVAL
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            30000,
            300,
            0
        );
        $this->assertLessThanOrEqual(SM2Algorithm::MAXIMUM_INTERVAL, $result['interval']);
    }

    public function testMaximumIntervalCapWithDelay(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            30000,
            300,
            10
        );
        $this->assertLessThanOrEqual(SM2Algorithm::MAXIMUM_INTERVAL, $result['interval']);
    }

    // ========================================================================
    // Interval never below 1
    // ========================================================================

    public function testIntervalNeverBelowOne(): void {
        // Even with tiny interval and Hard, should stay at least 1
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_HARD,
            1,
            250,
            0
        );
        $this->assertGreaterThanOrEqual(1, $result['interval']);
    }

    public function testIntervalNeverBelowOneAgain(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            0,
            250,
            0
        );
        $this->assertGreaterThanOrEqual(1, $result['interval']);
    }

    // ========================================================================
    // Delay days handling
    // ========================================================================

    public function testNegativeDelayIsClamped(): void {
        // Negative delay should be treated as 0
        $resultNeg = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            10,
            250,
            -5
        );
        $resultZero = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            10,
            250,
            0
        );
        $this->assertEquals($resultZero['interval'], $resultNeg['interval']);
        $this->assertEquals($resultZero['ease'], $resultNeg['ease']);
    }

    public function testDelayIncreasesEasyInterval(): void {
        $noDelay = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            10,
            250,
            0
        );
        $withDelay = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            10,
            250,
            10
        );
        $this->assertGreaterThan($noDelay['interval'], $withDelay['interval']);
    }

    // ========================================================================
    // predictIntervals
    // ========================================================================

    public function testPredictIntervalsReturnsAllRatings(): void {
        $predictions = $this->sm2->predictIntervals(10, 250, 0);
        $this->assertCount(4, $predictions);
        $this->assertArrayHasKey(0, $predictions);
        $this->assertArrayHasKey(1, $predictions);
        $this->assertArrayHasKey(2, $predictions);
        $this->assertArrayHasKey(3, $predictions);
    }

    public function testPredictIntervalsMonotonic(): void {
        // Higher ratings should give larger intervals
        $predictions = $this->sm2->predictIntervals(10, 250, 0);
        $this->assertEquals(1, $predictions[0]['interval']); // Again = reset
        $this->assertGreaterThanOrEqual($predictions[0]['interval'], $predictions[1]['interval']);
        $this->assertGreaterThanOrEqual($predictions[1]['interval'], $predictions[2]['interval']);
        $this->assertGreaterThanOrEqual($predictions[2]['interval'], $predictions[3]['interval']);
    }

    public function testPredictIntervalsNewCard(): void {
        $predictions = $this->sm2->predictIntervals(1, 250, 0);
        $this->assertEquals(1, $predictions[SM2Algorithm::RATING_AGAIN]['interval']);
        $this->assertEquals(1, $predictions[SM2Algorithm::RATING_HARD]['interval']);
        $this->assertEquals(3, $predictions[SM2Algorithm::RATING_GOOD]['interval']);
        $this->assertEquals(4, $predictions[SM2Algorithm::RATING_EASY]['interval']);
    }

    // ========================================================================
    // formatInterval
    // ========================================================================

    public function testFormatInterval(): void {
        $this->assertEquals('< 1d', $this->sm2->formatInterval(0));
        $this->assertEquals('1d', $this->sm2->formatInterval(1));
        $this->assertEquals('7d', $this->sm2->formatInterval(7));
        $this->assertEquals('29d', $this->sm2->formatInterval(29));
        // 30/30 = 1.0, PHP concatenation: 1.0 . 'mo' = '1mo'
        $this->assertEquals('1mo', $this->sm2->formatInterval(30));
        // 60/30 = 2.0 → '2mo'
        $this->assertEquals('2mo', $this->sm2->formatInterval(60));
        // 335 < 365 → months: 335/30 = 11.166... → '11.2mo'
        $this->assertEquals('11.2mo', $this->sm2->formatInterval(335));
        // 365/365 = 1.0 → '1y'
        $this->assertEquals('1y', $this->sm2->formatInterval(365));
        $this->assertEquals('2y', $this->sm2->formatInterval(730));
    }

    // ========================================================================
    // isValidRating
    // ========================================================================

    public function testIsValidRating(): void {
        $this->assertTrue($this->sm2->isValidRating(SM2Algorithm::RATING_AGAIN));
        $this->assertTrue($this->sm2->isValidRating(SM2Algorithm::RATING_HARD));
        $this->assertTrue($this->sm2->isValidRating(SM2Algorithm::RATING_GOOD));
        $this->assertTrue($this->sm2->isValidRating(SM2Algorithm::RATING_EASY));
        $this->assertFalse($this->sm2->isValidRating(-1));
        $this->assertFalse($this->sm2->isValidRating(4));
        $this->assertFalse($this->sm2->isValidRating(99));
    }

    // ========================================================================
    // Multi-step review sequences (simulating real study sessions)
    // ========================================================================

    public function testSequenceGoodGoodGoodGood(): void {
        // Simulate 4 consecutive Good ratings
        $interval = 1;
        $ease = 250;

        // Round 1: new → Good
        $r1 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $interval, $ease, 0);
        $this->assertEquals(3, $r1['interval']);  // (1)*2.5 = 2.5 → 3
        $this->assertEquals(250, $r1['ease']);

        // Round 2: Good
        $r2 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r1['interval'], $r1['ease'], 0);
        $this->assertEquals(8, $r2['interval']);  // (3)*2.5 = 7.5 → 8
        $this->assertEquals(250, $r2['ease']);

        // Round 3: Good
        $r3 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r2['interval'], $r2['ease'], 0);
        $this->assertEquals(20, $r3['interval']); // (8)*2.5 = 20
        $this->assertEquals(250, $r3['ease']);

        // Round 4: Good
        $r4 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r3['interval'], $r3['ease'], 0);
        $this->assertEquals(50, $r4['interval']); // (20)*2.5 = 50
        $this->assertEquals(250, $r4['ease']);

        // Verify monotonic growth
        $this->assertGreaterThan($r1['interval'], $r2['interval']);
        $this->assertGreaterThan($r2['interval'], $r3['interval']);
        $this->assertGreaterThan($r3['interval'], $r4['interval']);
    }

    public function testSequenceGoodGoodEasyGood(): void {
        $interval = 1;
        $ease = 250;

        // Round 1: Good
        $r1 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $interval, $ease, 0);
        // R2: Good
        $r2 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r1['interval'], $r1['ease'], 0);
        // R3: Easy — should boost ease and interval
        $r3 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_EASY, $r2['interval'], $r2['ease'], 0);
        // ease should be 250+20=270
        $this->assertEquals(270, $r3['ease']);
        // interval = (8)*270/100*1.3 = 28.08 → 28
        $this->assertEquals(28, $r3['interval']);
        // R4: Good
        $r4 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r3['interval'], $r3['ease'], 0);
        $this->assertEquals(270, $r4['ease']); // Good doesn't change ease
        $this->assertGreaterThan($r3['interval'], $r4['interval']);
    }

    public function testSequenceGoodGoodHardGood(): void {
        $interval = 1;
        $ease = 250;

        // Round 1: Good
        $r1 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $interval, $ease, 0);
        // R2: Good
        $r2 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r1['interval'], $r1['ease'], 0);

        // R3: Hard — ease drops, interval shrinks
        $r3 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_HARD, $r2['interval'], $r2['ease'], 0);
        $this->assertEquals(230, $r3['ease']); // 250-20
        $this->assertEquals(4, $r3['interval']); // (8)*0.5 = 4

        // R4: Good — interval grows from reduced ease
        $r4 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r3['interval'], $r3['ease'], 0);
        $this->assertEquals(230, $r4['ease']); // Good doesn't change ease
        $this->assertEquals(9, $r4['interval']); // (4)*230/100 = 9.2 → 9

        // Interval after Hard+Good should be lower than if it were Good+Good
        $this->assertLessThan(20, $r4['interval']);
    }

    public function testSequenceGoodGoodAgainGood(): void {
        $interval = 1;
        $ease = 250;

        // R1: Good
        $r1 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $interval, $ease, 0);
        // R2: Good
        $r2 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r1['interval'], $r1['ease'], 0);

        // R3: Again — full reset
        $r3 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_AGAIN, $r2['interval'], $r2['ease'], 0);
        $this->assertEquals(230, $r3['ease']); // 250-20
        $this->assertEquals(1, $r3['interval']); // Reset

        // R4: Good — start rebuilding from lowered ease
        $r4 = $this->sm2->calculateNextReview(SM2Algorithm::RATING_GOOD, $r3['interval'], $r3['ease'], 0);
        $this->assertEquals(230, $r4['ease']);
        $this->assertEquals(2, $r4['interval']); // (1)*230/100 = 2.3 → 2
    }

    // ========================================================================
    // Large interval scenarios (mature cards)
    // ========================================================================

    public function testMatureCardEasy(): void {
        // Card with interval=367, ease=366 (real-world data from SR-ANALYSIS)
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_EASY,
            367,
            366,
            0
        );
        // (367)*386/100*1.3 = 1841.606 → round → 1842
        // But capped at MAX_INTERVAL=36525, so no cap issue here
        $this->assertEquals(386, $result['ease']); // 366+20
        $this->assertGreaterThan(367, $result['interval']);
    }

    public function testMatureCardGood(): void {
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_GOOD,
            367,
            366,
            0
        );
        // (367)*366/100 = 1343.22 → round → 1343
        $this->assertEquals(366, $result['ease']);
        $this->assertEquals(1343, $result['interval']);
    }

    public function testMatureCardAgainDoesNotDestroyEaseCompletely(): void {
        // Even after Again on a mature card, ease drops but stays >= MIN_EASE
        $result = $this->sm2->calculateNextReview(
            SM2Algorithm::RATING_AGAIN,
            367,
            366,
            0
        );
        $this->assertEquals(346, $result['ease']); // 366-20
        $this->assertEquals(1, $result['interval']); // Reset to 1
        $this->assertGreaterThanOrEqual(SM2Algorithm::MIN_EASE, $result['ease']);
    }
}
