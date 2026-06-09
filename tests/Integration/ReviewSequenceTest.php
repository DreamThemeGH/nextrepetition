<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Review Sequence Integration Tests
 *
 * Simulates multi-step study sessions to verify interval growth
 * across different rating combinations. Tests the core SR logic
 * through repeated reviews of the same card.
 *
 * Sequence scenarios:
 *   A) Good→Good→Good→Good  (steady growth)
 *   B) Good→Good→Easy→Good  (accelerated by Easy)
 *   C) Good→Good→Hard→Good  (dip after Hard)
 *   D) Good→Good→Again→Good (reset after Again)
 *   E) All Easy              (maximum growth)
 *   F) All Again             (stuck at minimum)
 */

namespace OCA\Flashcards\Tests\Integration;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use PHPUnit\Framework\TestCase;

class ReviewSequenceTest extends TestCase {
    private SM2Algorithm $sm2;

    protected function setUp(): void {
        $this->sm2 = new SM2Algorithm();
    }

    /**
     * Helper: Run a sequence of reviews and return the history.
     *
     * @param int[] $ratings Sequence of ratings to apply
     * @param int $initialInterval Starting interval (default 1 = new card)
     * @param int $initialEase Starting ease (default 250)
     * @param int $delayDays Days overdue before each review
     * @return array[] Array of {interval, ease} after each step
     */
    private function runSequence(
        array $ratings,
        int $initialInterval = 1,
        int $initialEase = 250,
        int $delayDays = 0,
    ): array {
        $history = [];
        $interval = $initialInterval;
        $ease = $initialEase;

        foreach ($ratings as $rating) {
            $result = $this->sm2->calculateNextReview($rating, $interval, $ease, $delayDays);
            $interval = $result['interval'];
            $ease = $result['ease'];
            $history[] = ['interval' => $interval, 'ease' => $ease, 'rating' => $rating];
        }

        return $history;
    }

    // ========================================================================
    // Scenario A: Good→Good→Good→Good (steady growth)
    // ========================================================================

    public function testSequenceAllGood(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
        ]);

        // Verify monotonic growth
        $this->assertGreaterThan($history[0]['interval'], $history[1]['interval']);
        $this->assertGreaterThan($history[1]['interval'], $history[2]['interval']);
        $this->assertGreaterThan($history[2]['interval'], $history[3]['interval']);

        // Ease unchanged with Good
        foreach ($history as $step) {
            $this->assertEquals(250, $step['ease']);
        }

        // Expected values: 3, 8, 20, 50
        $this->assertEquals(3, $history[0]['interval']);
        $this->assertEquals(8, $history[1]['interval']);
        $this->assertEquals(20, $history[2]['interval']);
        $this->assertEquals(50, $history[3]['interval']);
    }

    public function testSequenceAllGoodFiveRounds(): void {
        $history = $this->runSequence(array_fill(0, 5, SM2Algorithm::RATING_GOOD));

        $this->assertCount(5, $history);
        // Round 5: (50)*250/100 = 125
        $this->assertEquals(125, $history[4]['interval']);
    }

    // ========================================================================
    // Scenario B: Good→Good→Easy→Good (accelerated by Easy)
    // ========================================================================

    public function testSequenceGoodGoodEasyGood(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_EASY,
            SM2Algorithm::RATING_GOOD,
        ]);

        // Steps 1-2: Good
        $this->assertEquals(3, $history[0]['interval']);
        $this->assertEquals(8, $history[1]['interval']);

        // Step 3: Easy — ease jumps to 270, interval = (8)*270/100*1.3 = 28
        $this->assertEquals(270, $history[2]['ease']);
        $this->assertEquals(28, $history[2]['interval']);

        // Step 4: Good — with elevated ease, interval = (28)*270/100 = 76
        $this->assertEquals(270, $history[3]['ease']);
        $this->assertEquals(76, $history[3]['interval']);

        // Verify Easy-accelerated sequence outruns All-Good
        $allGoodHistory = $this->runSequence(array_fill(0, 4, SM2Algorithm::RATING_GOOD));
        $this->assertGreaterThan($allGoodHistory[3]['interval'], $history[3]['interval']);
    }

    // ========================================================================
    // Scenario C: Good→Good→Hard→Good (dip after Hard)
    // ========================================================================

    public function testSequenceGoodGoodHardGood(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_HARD,
            SM2Algorithm::RATING_GOOD,
        ]);

        // Steps 1-2: Good → interval=3,8, ease=250
        $this->assertEquals(8, $history[1]['interval']);

        // Step 3: Hard → ease=230, interval=(8)*0.5=4
        $this->assertEquals(230, $history[2]['ease']);
        $this->assertEquals(4, $history[2]['interval']);

        // Step 4: Good → interval=(4)*230/100=9
        $this->assertEquals(230, $history[3]['ease']);
        $this->assertEquals(9, $history[3]['interval']);

        // After Hard+Good, interval should be lower than Good+Good
        $this->assertLessThan(20, $history[3]['interval']);
    }

    public function testHardDoesNotFullyReset(): void {
        // This is the critical test from SR-ANALYSIS.md BUG 1
        // Hard should REDUCE interval (×0.5) but NOT reset to 1
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_HARD,
        ]);

        // After 2xGood: interval=8
        $this->assertEquals(8, $history[1]['interval']);

        // After Hard: interval should be 4 (8*0.5), NOT 1 (reset)
        $this->assertEquals(4, $history[2]['interval']);
        $this->assertGreaterThan(1, $history[2]['interval'], 'Hard should NOT reset interval to 1');
    }

    // ========================================================================
    // Scenario D: Good→Good→Again→Good (reset after Again)
    // ========================================================================

    public function testSequenceGoodGoodAgainGood(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_AGAIN,
            SM2Algorithm::RATING_GOOD,
        ]);

        // Steps 1-2: Good → interval=3,8, ease=250
        $this->assertEquals(8, $history[1]['interval']);

        // Step 3: Again → FULL RESET, ease=230, interval=1
        $this->assertEquals(230, $history[2]['ease']);
        $this->assertEquals(1, $history[2]['interval']);

        // Step 4: Good → interval=(1)*230/100=2
        $this->assertEquals(230, $history[3]['ease']);
        $this->assertEquals(2, $history[3]['interval']);
    }

    public function testAgainResetsInterval(): void {
        // After building up a large interval, Again should reset to 1
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_AGAIN,
        ]);

        // After 3xGood: interval=20
        $this->assertEquals(20, $history[2]['interval']);

        // After Again: interval=1
        $this->assertEquals(1, $history[3]['interval']);
    }

    // ========================================================================
    // Scenario E: All Easy (maximum growth)
    // ========================================================================

    public function testSequenceAllEasy(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_EASY,
            SM2Algorithm::RATING_EASY,
            SM2Algorithm::RATING_EASY,
        ]);

        // Ease increases by 20 each Easy
        $this->assertEquals(270, $history[0]['ease']);
        $this->assertEquals(290, $history[1]['ease']);
        $this->assertEquals(310, $history[2]['ease']);

        // Intervals grow rapidly with Easy bonus
        // R1: (1)*270/100*1.3 = 3.51 → 4
        // R2: (4)*290/100*1.3 = 15.08 → 15
        // R3: (15)*310/100*1.3 = 60.45 → 60
        $this->assertEquals(4, $history[0]['interval']);
        $this->assertEquals(15, $history[1]['interval']);
        $this->assertEquals(60, $history[2]['interval']);

        // Verify monotonic growth
        $this->assertGreaterThan($history[0]['interval'], $history[1]['interval']);
        $this->assertGreaterThan($history[1]['interval'], $history[2]['interval']);
    }

    // ========================================================================
    // Scenario F: All Again (stuck at minimum)
    // ========================================================================

    public function testSequenceAllAgain(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_AGAIN,
            SM2Algorithm::RATING_AGAIN,
            SM2Algorithm::RATING_AGAIN,
        ]);

        // Interval stays at 1
        foreach ($history as $step) {
            $this->assertEquals(1, $step['interval']);
        }

        // Ease decreases to MIN_EASE and stays there
        $this->assertEquals(230, $history[0]['ease']); // 250-20
        $this->assertEquals(210, $history[1]['ease']); // 230-20 but wait... MIN_EASE=130
        // Actually: 250→230→210→190... 210 is still > 130
        
        // After enough Again, ease should floor at MIN_EASE (130)
        $manyAgains = $this->runSequence(array_fill(0, 20, SM2Algorithm::RATING_AGAIN));
        $last = end($manyAgains);
        $this->assertEquals(SM2Algorithm::MIN_EASE, $last['ease']);
        $this->assertEquals(1, $last['interval']);
    }

    // ========================================================================
    // Scenario G: Hard→Hard→Hard (ease degradation)
    // ========================================================================

    public function testSequenceHardDegradesEase(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_HARD,
            SM2Algorithm::RATING_HARD,
            SM2Algorithm::RATING_HARD,
            SM2Algorithm::RATING_HARD,
        ], 1, 250);

        // Ease decreases by 20 each Hard
        $this->assertEquals(230, $history[0]['ease']);
        $this->assertEquals(210, $history[1]['ease']);
        $this->assertEquals(190, $history[2]['ease']);
        $this->assertEquals(170, $history[3]['ease']);
    }

    // ========================================================================
    // Mixed sequence with delayed reviews
    // ========================================================================

    public function testSequenceWithDelayDays(): void {
        // Simulate reviewing a card that's overdue by 5 days each time
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
        ], 10, 250, 5);

        // delayDays=5
        // R1: (10+2.5)*250/100 = 31.25 → 31
        $this->assertEquals(31, $history[0]['interval']);

        // R2: (31+2.5)*250/100 = 83.75 → 84
        $this->assertEquals(84, $history[1]['interval']);
    }

    // ========================================================================
    // Recovery sequence: Again→Good→Good→Good
    // ========================================================================

    public function testRecoveryAfterAgain(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_AGAIN,   // Reset to 1
            SM2Algorithm::RATING_GOOD,   // 2
            SM2Algorithm::RATING_GOOD,   // 5
            SM2Algorithm::RATING_GOOD,   // 12
        ], 8, 250); // Starting from reviewed card with interval=8

        // Again: interval=1, ease=230
        $this->assertEquals(1, $history[0]['interval']);
        $this->assertEquals(230, $history[0]['ease']);

        // Recovery: rebuilding with lowered ease
        // Good: (1)*230/100 = 2.3 → 2
        $this->assertEquals(2, $history[1]['interval']);

        // Good: (2)*230/100 = 4.6 → 5
        $this->assertEquals(5, $history[2]['interval']);

        // Good: (5)*230/100 = 11.5 → 12
        $this->assertEquals(12, $history[3]['interval']);

        // Compare with All-Good sequence from new: 3,8,20,50
        // Recovery is slower due to lowered ease (230 vs 250)
    }

    // ========================================================================
    // Ease boundary: very high ease
    // ========================================================================

    public function testSequenceHighEaseMatureCard(): void {
        // Real-world scenario: card with ease=366, interval=367 (from SR-ANALYSIS)
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,
            SM2Algorithm::RATING_GOOD,
        ], 367, 366);

        // R1: (367)*366/100 = 1343.22 → 1343
        $this->assertEquals(1343, $history[0]['interval']);
        $this->assertEquals(366, $history[0]['ease']);

        // R2: (1343)*366/100 = 4915.38 → 4915
        $this->assertEquals(4915, $history[1]['interval']);

        // But MAX_INTERVAL=36525, so we shouldn't exceed that
        $this->assertLessThanOrEqual(SM2Algorithm::MAXIMUM_INTERVAL, $history[1]['interval']);
    }

    // ========================================================================
    // Interval cap at maximum
    // ========================================================================

    public function testSequenceHitsMaximumInterval(): void {
        // Start near the cap and do Easy
        $history = $this->runSequence([
            SM2Algorithm::RATING_EASY,
            SM2Algorithm::RATING_EASY,
        ], 30000, 300);

        // Should cap at MAX_INTERVAL
        foreach ($history as $step) {
            $this->assertLessThanOrEqual(SM2Algorithm::MAXIMUM_INTERVAL, $step['interval']);
        }
    }

    // ========================================================================
    // Combined: all 4 ratings in sequence
    // ========================================================================

    public function testSequenceAllRatingsMixed(): void {
        $history = $this->runSequence([
            SM2Algorithm::RATING_GOOD,    // 3
            SM2Algorithm::RATING_EASY,    // 11
            SM2Algorithm::RATING_HARD,    // reduced
            SM2Algorithm::RATING_AGAIN,   // reset
            SM2Algorithm::RATING_GOOD,    // rebuild
        ]);

        // Verify the sequence produces reasonable, non-decreasing behavior
        $this->assertCount(5, $history);

        // Good: interval grows
        $this->assertEquals(3, $history[0]['interval']);

        // Easy: interval grows further
        $this->assertGreaterThan($history[0]['interval'], $history[1]['interval']);

        // Hard: interval drops (but not to 1)
        $this->assertLessThan($history[1]['interval'], $history[2]['interval']);
        $this->assertGreaterThan(1, $history[2]['interval']);

        // Again: interval = 1
        $this->assertEquals(1, $history[3]['interval']);

        // Good after Again: rebuild starts
        $this->assertGreaterThanOrEqual(1, $history[4]['interval']);
    }
}
