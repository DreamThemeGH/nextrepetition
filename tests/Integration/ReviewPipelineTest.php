<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Review Pipeline Integration Tests
 *
 * End-to-end test of the full review flow:
 *   1. Parse .md file content into cards
 *   2. Submit review ratings via SM2Service
 *   3. Serialize updated cards back to .md
 *   4. Re-parse the output and verify SR metadata
 *
 * These tests verify that the complete pipeline produces correct
 * Obsidian-compatible SR tags in the markdown file.
 */

namespace OCA\Flashcards\Tests\Integration;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use OCA\Flashcards\Service\CardParserService;
use OCA\Flashcards\Service\CardSerializerService;
use OCA\Flashcards\Service\SM2Service;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReviewPipelineTest extends TestCase {
    private CardParserService $parser;
    private CardSerializerService $serializer;
    private SM2Service $sm2Service;

    protected function setUp(): void {
        $logger = $this->createMock(LoggerInterface::class);
        $this->parser = new CardParserService($logger);
        $this->serializer = new CardSerializerService($logger);
        $this->sm2Service = new SM2Service(new SM2Algorithm(), $logger);
    }

    /**
     * Helper: Run pipeline — parse, review a card, serialize, re-parse.
     *
     * @return array The re-parsed card from the output
     */
    private function reviewAndVerify(
        string $content,
        int $cardIndex,
        int $rating,
        int $srIndex = 0,
    ): array {
        // 1. Parse
        $parsed = $this->parser->parse($content);

        $this->assertArrayHasKey($cardIndex, $parsed['cards'], "Card index {$cardIndex} not found");

        $card = $parsed['cards'][$cardIndex];

        // 2. Review
        $newSR = $this->sm2Service->processReview($card, $rating, $srIndex);
        $parsed['cards'][$cardIndex]['sr'] = $newSR;

        // 3. Serialize
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // 4. Re-parse
        $reparsed = $this->parser->parse($serialized);

        $this->assertArrayHasKey($cardIndex, $reparsed['cards']);
        return $reparsed['cards'][$cardIndex];
    }

    // ========================================================================
    // New card → first review (all 4 ratings)
    // ========================================================================

    public function testNewCardFirstReviewEasy(): void {
        $content = "## Test\n\nhello:::привет\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_EASY);

        $this->assertCount(1, $result['sr']);
        $this->assertEquals(270, $result['sr'][0]['ease']);       // 250+20
        $this->assertEquals(4, $result['sr'][0]['interval']);      // (1)*270/100*1.3 ≈ 4
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result['sr'][0]['date']);
        $this->assertNotEquals('2000-01-01', $result['sr'][0]['date']);
        // After review with future date, state should be 'review' (not due)
        $this->assertEquals('review', $result['state']);
    }

    public function testNewCardFirstReviewGood(): void {
        $content = "## Test\n\nhello:::привет\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        $this->assertEquals(250, $result['sr'][0]['ease']);
        $this->assertEquals(3, $result['sr'][0]['interval']);      // (1)*250/100 ≈ 3
        $this->assertNotEquals('2000-01-01', $result['sr'][0]['date']);
    }

    public function testNewCardFirstReviewHard(): void {
        $content = "## Test\n\nhello:::привет\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_HARD);

        $this->assertEquals(230, $result['sr'][0]['ease']);       // 250-20
        $this->assertEquals(1, $result['sr'][0]['interval']);      // max(1, 1*0.5)
        $this->assertNotEquals('2000-01-01', $result['sr'][0]['date']);
    }

    public function testNewCardFirstReviewAgain(): void {
        $content = "## Test\n\nhello:::привет\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_AGAIN);

        $this->assertEquals(230, $result['sr'][0]['ease']);       // 250-20
        $this->assertEquals(1, $result['sr'][0]['interval']);      // reset to 1
        $this->assertNotEquals('2000-01-01', $result['sr'][0]['date']);
    }

    // ========================================================================
    // Previously reviewed card → next review
    // ========================================================================

    public function testReviewedCardNextReviewGood(): void {
        // Card reviewed yesterday with interval=3, ease=250
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        // delayDays=1 → (3+0.5)*250/100 = 8.75 → 9
        $this->assertEquals(250, $result['sr'][0]['ease']);
        $this->assertEquals(9, $result['sr'][0]['interval']);
    }

    public function testReviewedCardNextReviewEasy(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_EASY);

        // delayDays=1 → (3+1)*270/100*1.3 = 14.04 → 14
        $this->assertEquals(270, $result['sr'][0]['ease']);
        $this->assertEquals(14, $result['sr'][0]['interval']);
    }

    public function testReviewedCardNextReviewAgain(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},30,270-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_AGAIN);

        $this->assertEquals(250, $result['sr'][0]['ease']);       // 270-20
        $this->assertEquals(1, $result['sr'][0]['interval']);      // reset
    }

    // ========================================================================
    // Overdue card review
    // ========================================================================

    public function testOverdueCardReviewGood(): void {
        // Card was due 10 days ago
        $tenDaysAgo = (new \DateTime('-10 days'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$tenDaysAgo},10,250-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        // delayDays=10 → (10+5)*250/100 = 37.5 → 38
        $this->assertEquals(250, $result['sr'][0]['ease']);
        $this->assertEquals(38, $result['sr'][0]['interval']);
    }

    public function testOverdueCardReviewEasy(): void {
        $tenDaysAgo = (new \DateTime('-10 days'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$tenDaysAgo},10,250-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_EASY);

        // delayDays=10 → (10+10)*270/100*1.3 = 70.2 → 70
        $this->assertEquals(270, $result['sr'][0]['ease']);
        $this->assertEquals(70, $result['sr'][0]['interval']);
    }

    // ========================================================================
    // Dual-direction review
    // ========================================================================

    public function testDualDirectionReviewFirstDirection(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250!{$yesterday},5,260-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD, 0);

        $this->assertCount(2, $result['sr']);
        // Direction 0 updated (delayDays=1)
        $this->assertEquals(9, $result['sr'][0]['interval']);      // (3+0.5)*250/100
        // Direction 1 preserved
        $this->assertEquals(5, $result['sr'][1]['interval']);
        $this->assertEquals(260, $result['sr'][1]['ease']);
    }

    public function testDualDirectionReviewSecondDirection(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250!{$yesterday},5,260-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD, 1);

        $this->assertCount(2, $result['sr']);
        // Direction 0 preserved
        $this->assertEquals(3, $result['sr'][0]['interval']);
        // Direction 1 updated (delayDays=1)
        $this->assertEquals(14, $result['sr'][1]['interval']);     // (5+0.5)*260/100 = 14.3 → 14
    }

    // ========================================================================
    // Cloze card pipeline
    // ========================================================================

    public function testClozeCardReview(): void {
        $content = "## Cloze\n\nI ==like==^[люблю] pizza\nпицца\n<!--SR:!2026-06-01,5,250-->\n";

        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        $this->assertEquals('cloze', $result['type']);
        $this->assertCount(1, $result['sr']);
        $this->assertNotEquals('2000-01-01', $result['sr'][0]['date']);
    }

    public function testMultiClozeReview(): void {
        $content = "## Multi\n\n==Ja== ==volim== picu\nпицца\n<!--SR:!2026-06-01,5,250!2026-05-28,3,260-->\n";

        // Parse
        $parsed = $this->parser->parse($content);
        $this->assertCount(2, $parsed['cards']); // 2 clozes → 2 cards

        // Review first cloze
        $card0 = $parsed['cards'][0];
        $newSR0 = $this->sm2Service->processReview($card0, SM2Algorithm::RATING_GOOD);

        // Review second cloze
        $card1 = $parsed['cards'][1];
        $newSR1 = $this->sm2Service->processReview($card1, SM2Algorithm::RATING_EASY);

        $this->assertEquals(250, $newSR0[0]['ease']);
        $this->assertEquals(280, $newSR1[0]['ease']); // 260+20

        // Serialize all cards back (this is tricky for multi-cloze)
        // The serializer receives all cards — need to merge back
        // For multi-cloze, both cards share the same SR line
        // This tests the serializer's handling
        $parsed['cards'][0]['sr'] = $newSR0;
        $parsed['cards'][1]['sr'] = $newSR1;
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // Re-parse
        $reparsed = $this->parser->parse($serialized);
        $this->assertCount(2, $reparsed['cards']);
    }

    // ========================================================================
    // Multiple cards in one file
    // ========================================================================

    public function testMultipleCardsReviewInSequence(): void {
        $content = "## Deck\n\ncard1:::транс1\n<!--SR:!2026-06-01,5,250-->\n\ncard2:::транс2\n";

        // Parse
        $parsed = $this->parser->parse($content);
        $this->assertCount(2, $parsed['cards']);

        // Review card 0
        $newSR0 = $this->sm2Service->processReview($parsed['cards'][0], SM2Algorithm::RATING_GOOD);
        $parsed['cards'][0]['sr'] = $newSR0;

        // Review card 1 (new card)
        $newSR1 = $this->sm2Service->processReview($parsed['cards'][1], SM2Algorithm::RATING_EASY);
        $parsed['cards'][1]['sr'] = $newSR1;

        // Serialize
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // Re-parse and verify both cards have correct SR
        $reparsed = $this->parser->parse($serialized);
        $this->assertCount(2, $reparsed['cards']);

        // Card 0: reviewed card → Good
        $this->assertEquals(250, $reparsed['cards'][0]['sr'][0]['ease']);
        $this->assertEquals('review', $reparsed['cards'][0]['state']);

        // Card 1: new card → Easy
        $this->assertEquals(270, $reparsed['cards'][1]['sr'][0]['ease']); // 250+20
        $this->assertEquals('review', $reparsed['cards'][1]['state']);
    }

    // ========================================================================
    // SR tag format correctness
    // ========================================================================

    public function testSRTagFormatAfterReview(): void {
        $content = "## Test\n\nhello:::привет\n";
        $parsed = $this->parser->parse($content);

        $newSR = $this->sm2Service->processReview($parsed['cards'][0], SM2Algorithm::RATING_GOOD);
        $parsed['cards'][0]['sr'] = $newSR;

        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // Verify exact SR format: <!--SR:!YYYY-MM-DD,interval,ease-->
        $this->assertMatchesRegularExpression(
            '/<!--SR:!\d{4}-\d{2}-\d{2},\d+,\d+-->/',
            $serialized
        );
    }

    public function testSRTagFormatDualDirection(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250!{$yesterday},5,260-->\n";
        $parsed = $this->parser->parse($content);

        $newSR = $this->sm2Service->processReview($parsed['cards'][0], SM2Algorithm::RATING_GOOD, 0);
        $parsed['cards'][0]['sr'] = $newSR;

        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // Verify dual SR format
        $this->assertMatchesRegularExpression(
            '/<!--SR:!\d{4}-\d{2}-\d{2},\d+,\d+!\d{4}-\d{2}-\d{2},\d+,\d+-->/',
            $serialized
        );
    }

    // ========================================================================
    // State transitions in the pipeline
    // ========================================================================

    public function testStateTransitionsNewToReview(): void {
        $content = "## Test\n\nhello:::привет\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        $this->assertEquals('review', $result['state']);
    }

    public function testStateTransitionsDueToReview(): void {
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $content = "## Test\n\nhello:::привет\n<!--SR:!{$yesterday},3,250-->\n";
        $result = $this->reviewAndVerify($content, 0, SM2Algorithm::RATING_GOOD);

        $this->assertEquals('review', $result['state']);
    }
}
