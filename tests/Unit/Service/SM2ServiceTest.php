<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — SM2 Service Unit Tests
 *
 * Tests the SM2Service wrapper that bridges SM2Algorithm
 * with the file-based SR metadata format (<!--SR:!date,interval,ease-->).
 */

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use OCA\Flashcards\Service\SM2Service;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SM2ServiceTest extends TestCase {
    private SM2Service $sm2Service;
    private SM2Algorithm $algorithm;

    protected function setUp(): void {
        $this->algorithm = new SM2Algorithm();
        $logger = $this->createMock(LoggerInterface::class);
        $this->sm2Service = new SM2Service($this->algorithm, $logger);
    }

    // ========================================================================
    // Helper: create a card data array
    // ========================================================================

    private function makeCard(?array $sr = null): array {
        return ['sr' => $sr ?? []];
    }

    private function makeCardWithSR(array $srEntries): array {
        return ['sr' => $srEntries];
    }

    // ========================================================================
    // New card reviews (no prior SR data)
    // ========================================================================

    public function testProcessReviewNewCardEasy(): void {
        $card = $this->makeCard();
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_EASY);

        $this->assertCount(1, $result);
        $this->assertEquals(SM2Algorithm::DEFAULT_EASE + 20, $result[0]['ease']);
        $this->assertEquals(4, $result[0]['interval']); // (1)*270/100*1.3 = 3.51 → 4
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result[0]['date']);
    }

    public function testProcessReviewNewCardGood(): void {
        $card = $this->makeCard();
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);

        $this->assertCount(1, $result);
        $this->assertEquals(SM2Algorithm::DEFAULT_EASE, $result[0]['ease']);
        $this->assertEquals(3, $result[0]['interval']); // (1)*250/100 = 2.5 → 3
    }

    public function testProcessReviewNewCardHard(): void {
        $card = $this->makeCard();
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_HARD);

        $this->assertEquals(SM2Algorithm::DEFAULT_EASE - 20, $result[0]['ease']);
        $this->assertEquals(1, $result[0]['interval']); // max(1, (1)*0.5) = 1
    }

    public function testProcessReviewNewCardAgain(): void {
        $card = $this->makeCard();
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_AGAIN);

        $this->assertEquals(SM2Algorithm::DEFAULT_EASE - 20, $result[0]['ease']);
        $this->assertEquals(1, $result[0]['interval']);
    }

    // ========================================================================
    // New card with dummy SR entry (2000-01-01 = unreviewed)
    // ========================================================================

    public function testProcessReviewDummySREntry(): void {
        // Card has a dummy SR entry (unreviewed direction)
        $card = $this->makeCardWithSR([
            ['date' => '2000-01-01', 'interval' => 1, 'ease' => 250],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);

        // Should treat as new card
        $this->assertEquals(250, $result[0]['ease']);
        $this->assertEquals(3, $result[0]['interval']);
    }

    // ========================================================================
    // Reviewed card (has real SR data)
    // ========================================================================

    public function testProcessReviewReviewedCardGood(): void {
        // Card was due yesterday with interval=3, ease=250
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 3, 'ease' => 250],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);

        // delayDays=1, so interval = (3 + 1/2) * 250 / 100 = 8.75 → 9
        $this->assertEquals(250, $result[0]['ease']);
        $this->assertEquals(9, $result[0]['interval']);
    }

    public function testProcessReviewReviewedCardEasy(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 3, 'ease' => 250],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_EASY);

        // delayDays=1, ease=270, interval = (3+1)*270/100*1.3 = 14.04 → 14
        $this->assertEquals(270, $result[0]['ease']);
        $this->assertEquals(14, $result[0]['interval']);
    }

    public function testProcessReviewReviewedCardAgain(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 30, 'ease' => 270],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_AGAIN);

        // ease drops, interval resets
        $this->assertEquals(250, $result[0]['ease']); // 270-20
        $this->assertEquals(1, $result[0]['interval']);
    }

    // ========================================================================
    // Overdue card with significant delay
    // ========================================================================

    public function testProcessReviewOverdueCard(): void {
        // Card was due 10 days ago, interval=10, ease=250
        $tenDaysAgo = (new \DateTime('-10 days'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $tenDaysAgo, 'interval' => 10, 'ease' => 250],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);

        // delayDays=10, interval = (10 + 10/2) * 250 / 100 = 37.5 → 38
        $this->assertEquals(250, $result[0]['ease']);
        $this->assertEquals(38, $result[0]['interval']);
    }

    // ========================================================================
    // Future-dated card (not yet due, shouldn't happen in practice but test it)
    // ========================================================================

    public function testProcessReviewFutureCard(): void {
        // Card due in the future — delayDays should be 0
        $future = (new \DateTime('+5 days'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $future, 'interval' => 3, 'ease' => 250],
        ]);
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);

        // delayDays=0 (not overdue), interval = (3+0)*250/100 = 7.5 → 8
        $this->assertEquals(250, $result[0]['ease']);
        $this->assertEquals(8, $result[0]['interval']);
    }

    // ========================================================================
    // Dual-direction SR (srIndex 0 and 1)
    // ========================================================================

    public function testProcessReviewSecondDirection(): void {
        // Card has 2 SR entries: direction 0 (front→back) and dummy direction 1
        $card = $this->makeCardWithSR([
            ['date' => '2025-06-01', 'interval' => 10, 'ease' => 250],
            ['date' => '2000-01-01', 'interval' => 1, 'ease' => 250],
        ]);

        // Review direction 1 (back→front) — should be treated as new
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD, 1);

        $this->assertCount(2, $result);
        // Direction 0 preserved
        $this->assertEquals(10, $result[0]['interval']);
        // Direction 1 = new card → Good
        $this->assertEquals(250, $result[1]['ease']);
        $this->assertEquals(3, $result[1]['interval']);
    }

    public function testProcessReviewBothDirections(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 3, 'ease' => 250],
            ['date' => $yesterday, 'interval' => 5, 'ease' => 260],
        ]);

        // Review direction 0
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD, 0);

        $this->assertCount(2, $result);
        // Direction 0 updated
        $this->assertEquals(9, $result[0]['interval']); // (3+0.5)*250/100 = 8.75 → 9
        // Direction 1 preserved
        $this->assertEquals(5, $result[1]['interval']);
        $this->assertEquals(260, $result[1]['ease']);
    }

    // ========================================================================
    // SR entry array expansion (filling missing indices)
    // ========================================================================

    public function testProcessReviewExpandSRArray(): void {
        // Card with no SR entries at all, review direction 2
        $card = $this->makeCard();
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD, 2);

        // Should expand to 3 entries (indices 0, 1, 2)
        $this->assertCount(3, $result);
        // Index 0 and 1 should be dummy entries
        $this->assertEquals('2000-01-01', $result[0]['date']);
        $this->assertEquals('2000-01-01', $result[1]['date']);
        // Index 2 should be the reviewed entry
        $this->assertNotEquals('2000-01-01', $result[2]['date']);
        $this->assertEquals(3, $result[2]['interval']);
    }

    // ========================================================================
    // predictReview
    // ========================================================================

    public function testPredictReviewNewCard(): void {
        $card = $this->makeCard();
        $predictions = $this->sm2Service->predictReview($card);

        $this->assertCount(4, $predictions);
        $this->assertArrayHasKey(0, $predictions);
        $this->assertArrayHasKey(1, $predictions);
        $this->assertArrayHasKey(2, $predictions);
        $this->assertArrayHasKey(3, $predictions);

        // Verify expected intervals for new card
        $this->assertEquals(1, $predictions[SM2Algorithm::RATING_AGAIN]['interval']);
        $this->assertEquals(1, $predictions[SM2Algorithm::RATING_HARD]['interval']);
        $this->assertEquals(3, $predictions[SM2Algorithm::RATING_GOOD]['interval']);
        $this->assertEquals(4, $predictions[SM2Algorithm::RATING_EASY]['interval']);
    }

    public function testPredictReviewReviewedCard(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 3, 'ease' => 250],
        ]);
        $predictions = $this->sm2Service->predictReview($card);

        $this->assertCount(4, $predictions);
        // delayDays=1: Good interval = (3+0.5)*250/100 = 8.75 → 9
        $this->assertEquals(9, $predictions[SM2Algorithm::RATING_GOOD]['interval']);
        // Again resets
        $this->assertEquals(1, $predictions[SM2Algorithm::RATING_AGAIN]['interval']);
    }

    public function testPredictReviewIncludesDates(): void {
        $card = $this->makeCard();
        $predictions = $this->sm2Service->predictReview($card);

        foreach ($predictions as $rating => $pred) {
            $this->assertArrayHasKey('date', $pred);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $pred['date']);
            $this->assertArrayHasKey('label', $pred);
        }
    }

    public function testPredictReviewMonotonicIntervals(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 10, 'ease' => 250],
        ]);
        $predictions = $this->sm2Service->predictReview($card);

        // Higher ratings → higher intervals (Again=0 < Hard=1 < Good=2 < Easy=3)
        $this->assertGreaterThanOrEqual(
            $predictions[SM2Algorithm::RATING_HARD]['interval'],
            $predictions[SM2Algorithm::RATING_GOOD]['interval']
        );
        $this->assertGreaterThanOrEqual(
            $predictions[SM2Algorithm::RATING_GOOD]['interval'],
            $predictions[SM2Algorithm::RATING_EASY]['interval']
        );
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function testProcessReviewPreservesUnchangedDirections(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $card = $this->makeCardWithSR([
            ['date' => $yesterday, 'interval' => 3, 'ease' => 250],
            ['date' => '2025-06-01', 'interval' => 10, 'ease' => 270],
        ]);

        // Review only direction 0
        $result = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD, 0);

        // Direction 1 should remain unchanged
        $this->assertEquals(10, $result[1]['interval']);
        $this->assertEquals(270, $result[1]['ease']);
        $this->assertEquals('2025-06-01', $result[1]['date']);
    }

    public function testProcessReviewMultipleRounds(): void {
        // Simulate studying the same card 3 times in a row
        $card = $this->makeCard();

        // Round 1: Good
        $sr1 = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);
        $card['sr'] = $sr1;

        // Adjust the date to simulate immediate re-review (make it "yesterday")
        $sr1[0]['date'] = (new \DateTime('-1 day'))->format('Y-m-d');
        $card['sr'] = $sr1;

        // Round 2: Good
        $sr2 = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);
        $this->assertGreaterThan($sr1[0]['interval'], $sr2[0]['interval']);

        // Round 3: Good
        $sr2[0]['date'] = (new \DateTime('-1 day'))->format('Y-m-d');
        $card['sr'] = $sr2;
        $sr3 = $this->sm2Service->processReview($card, SM2Algorithm::RATING_GOOD);
        $this->assertGreaterThan($sr2[0]['interval'], $sr3[0]['interval']);
    }
}
