<?php

declare(strict_types=1);

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\CardParserService;
use PHPUnit\Framework\TestCase;

class CardParserServiceTest extends TestCase {
    private CardParserService $parser;

    protected function setUp(): void {
        $this->parser = new CardParserService(
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );
    }

    public function testParseBasicCard(): void {
        // Tag format is #flashcards/path — "## Tag1" is a heading, not a tag
        $content = "## MyDeck\n\nword:::translation\n";
        $result = $this->parser->parse($content);

        // "## MyDeck" is a heading, tag remains empty
        $this->assertEquals('', $result['tag']);
        $this->assertCount(1, $result['cards']);
        $card = $result['cards'][0];
        $this->assertEquals('basic', $card['type']);
        $this->assertEquals('word', $card['front']);
        $this->assertEquals('translation', $card['back']);
    }

    public function testParseTranscriptionCard(): void {
        $content = "## Words\n\nhello [ həˈloʊ ] ::: привет\n";
        $result = $this->parser->parse($content);

        $this->assertCount(1, $result['cards']);
        $card = $result['cards'][0];
        $this->assertEquals('basic', $card['type']);
        $this->assertEquals('hello', $card['front']);
        $this->assertEquals('həˈloʊ', $card['transcription']);
        $this->assertEquals('привет', $card['back']);
    }

    public function testParseClozeCard(): void {
        $content = "## Cloze\n\nI ==like==^[люблю] pizza\n";
        $result = $this->parser->parse($content);

        $this->assertCount(1, $result['cards']);
        $card = $result['cards'][0];
        $this->assertEquals('cloze', $card['type']);
        $this->assertCount(1, $card['clozes']);
        $this->assertEquals('like', $card['clozes'][0]['word']);
        $this->assertEquals('люблю', $card['clozes'][0]['hint']);
    }

    public function testParseMultiCloze(): void {
        $content = "## Multi\n\n==Ja== ==volim== picu\n";
        $result = $this->parser->parse($content);

        $this->assertCount(1, $result['cards']);
        $card = $result['cards'][0];
        $this->assertEquals('cloze', $card['type']);
        $this->assertCount(2, $card['clozes']);
        $this->assertEquals('Ja', $card['clozes'][0]['word']);
        $this->assertEquals('volim', $card['clozes'][1]['word']);
    }

    public function testParseSRMetadata(): void {
        $content = "## Test\n\nword:::translation\n<!--SR:!2025-06-15,10,270-->\n";
        $result = $this->parser->parse($content);

        $this->assertCount(1, $result['cards']);
        $card = $result['cards'][0];
        $this->assertCount(1, $card['sr']);
        $this->assertEquals('2025-06-15', $card['sr'][0]['date']);
        $this->assertEquals(10, $card['sr'][0]['interval']);
        $this->assertEquals(270, $card['sr'][0]['ease']);
    }

    public function testParseDualSR(): void {
        $content = "## Test\n\nword:::translation\n<!--SR:!2025-06-15,10,270!2025-06-10,5,250-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertCount(2, $card['sr']);
        $this->assertEquals(10, $card['sr'][0]['interval']);
        $this->assertEquals(5, $card['sr'][1]['interval']);
    }

    public function testParseEmptyContent(): void {
        $result = $this->parser->parse('');
        $this->assertCount(0, $result['cards']);
    }

    public function testParseNoCards(): void {
        $content = "## Just a heading\n\nSome text\n\nMore text\n";
        $result = $this->parser->parse($content);
        $this->assertCount(0, $result['cards']);
    }

    public function testDetectGitConflicts(): void {
        $content = "## Test\n\nword:::translation\n<<<<<<< HEAD\n<!--SR:!2025-06-15,10,270-->\n=======\n<!--SR:!2025-06-14,8,260-->\n>>>>>>> abc123\n";
        $result = $this->parser->parse($content);
        $this->assertCount(1, $result['conflicts']);
    }

    public function testQuickScanCountsDueCards(): void {
        $today = date('Y-m-d');
        $content = "## Test\n\nword1:::trans1\n<!--SR:!{$today},1,250-->\nword2:::trans2\n";
        $result = $this->parser->quickScan($content);

        $this->assertEquals(2, $result['total']);
        $this->assertGreaterThanOrEqual(1, $result['due']);
        $this->assertGreaterThanOrEqual(1, $result['new']);
    }

    public function testCardStateNew(): void {
        $content = "## Test\n\nword:::translation\n";
        $result = $this->parser->parse($content);
        $this->assertEquals('new', $result['cards'][0]['state']);
    }

    public function testCardStateReview(): void {
        $future = date('Y-m-d', strtotime('+30 days'));
        $content = "## Test\n\nword:::translation\n<!--SR:!{$future},30,270-->\n";
        $result = $this->parser->parse($content);
        $this->assertEquals('review', $result['cards'][0]['state']);
    }

    public function testCardStateDue(): void {
        $past = date('Y-m-d', strtotime('-1 day'));
        $content = "## Test\n\nword:::translation\n<!--SR:!{$past},5,250-->\n";
        $result = $this->parser->parse($content);
        $this->assertEquals('due', $result['cards'][0]['state']);
    }

    // ========================================================================
    // SR metadata edge cases
    // ========================================================================

    public function testParseSRDummyDate(): void {
        // 2000-01-01 is the dummy date for unreviewed directions
        $content = "## Test\n\nword:::translation\n<!--SR:!2000-01-01,1,250-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertCount(1, $card['sr']);
        $this->assertEquals('2000-01-01', $card['sr'][0]['date']);
        $this->assertEquals(1, $card['sr'][0]['interval']);
        $this->assertEquals(250, $card['sr'][0]['ease']);
        // With only dummy entries, state should be 'new'
        $this->assertEquals('new', $card['state']);
    }

    public function testParseSRMixedRealAndDummy(): void {
        // One real SR entry and one dummy
        $future = date('Y-m-d', strtotime('+30 days'));
        $content = "## Test\n\nword:::translation\n<!--SR:!{$future},30,270!2000-01-01,1,250-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertCount(2, $card['sr']);
        $this->assertEquals($future, $card['sr'][0]['date']);
        $this->assertEquals('2000-01-01', $card['sr'][1]['date']);
        // Has real SR and not due → 'review'
        $this->assertEquals('review', $card['state']);
    }

    public function testParseSRExactlyToday(): void {
        // Card due exactly today
        $today = date('Y-m-d');
        $content = "## Test\n\nword:::translation\n<!--SR:!{$today},3,250-->\n";
        $result = $this->parser->parse($content);

        $this->assertEquals('due', $result['cards'][0]['state']);
    }

    public function testParseSRMultipleEntiresAllFuture(): void {
        $future1 = date('Y-m-d', strtotime('+5 days'));
        $future2 = date('Y-m-d', strtotime('+10 days'));
        $content = "## Test\n\nword:::translation\n<!--SR:!{$future1},5,250!{$future2},10,260-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertCount(2, $card['sr']);
        // Both future → not due
        $this->assertEquals('review', $card['state']);
    }

    public function testParseSRMultipleEntiresOneDue(): void {
        $future = date('Y-m-d', strtotime('+5 days'));
        $past = date('Y-m-d', strtotime('-2 days'));
        $content = "## Test\n\nword:::translation\n<!--SR:!{$future},5,250!{$past},3,260-->\n";
        $result = $this->parser->parse($content);

        // At least one is due → state = 'due'
        $this->assertEquals('due', $result['cards'][0]['state']);
    }

    public function testParseSRWithLargeValues(): void {
        // Real-world data: large interval and ease
        $content = "## Test\n\nword:::translation\n<!--SR:!2026-12-31,367,366-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertEquals(367, $card['sr'][0]['interval']);
        $this->assertEquals(366, $card['sr'][0]['ease']);
    }

    public function testParseSRWithoutMetadata(): void {
        // Card without SR tag
        $content = "## Test\n\nword:::translation\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertEmpty($card['sr']);
        $this->assertEquals('', $card['srRaw']);
        $this->assertEquals(-1, $card['srLine']);
        $this->assertEquals('new', $card['state']);
    }

    public function testParseMultiClozeWithSR(): void {
        // Multi-cloze card with 2 cloze positions and 2 SR entries
        $content = "## Test\n\n==Ja== ==volim== picu\nпицца\n<!--SR:!2026-06-05,10,250!2026-06-01,5,260-->\n";
        $result = $this->parser->parse($content);

        $this->assertCount(2, $result['cards']);
        // First card = cloze 0
        $this->assertEquals('cloze', $result['cards'][0]['type']);
        $this->assertEquals(0, $result['cards'][0]['clozeIndex']);
        $this->assertCount(1, $result['cards'][0]['sr']);
        $this->assertEquals(10, $result['cards'][0]['sr'][0]['interval']);
        // Second card = cloze 1
        $this->assertEquals('cloze', $result['cards'][1]['type']);
        $this->assertEquals(1, $result['cards'][1]['clozeIndex']);
        $this->assertCount(1, $result['cards'][1]['sr']);
        $this->assertEquals(5, $result['cards'][1]['sr'][0]['interval']);
    }

    public function testParseMultipleCardsMixed(): void {
        $content = "## Test\n\nword1:::trans1\n<!--SR:!2026-06-01,5,250-->\n\nword2:::trans2\n\n==like== pizza\nпицца\n<!--SR:!2026-06-03,3,260-->\n";
        $result = $this->parser->parse($content);

        $this->assertCount(3, $result['cards']);
        // Card 0: basic with SR
        $this->assertEquals('basic', $result['cards'][0]['type']);
        $this->assertEquals(5, $result['cards'][0]['sr'][0]['interval']);
        // Card 1: basic without SR
        $this->assertEquals('basic', $result['cards'][1]['type']);
        $this->assertEmpty($result['cards'][1]['sr']);
        // Card 2: cloze with SR
        $this->assertEquals('cloze', $result['cards'][2]['type']);
        $this->assertEquals(3, $result['cards'][2]['sr'][0]['interval']);
    }

    public function testParseSRPreservesRawFlag(): void {
        $content = "## Test\n\nword:::translation\n<!--SR:!2026-06-05,15,270-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertStringContainsString('2026-06-05,15,270', $card['srRaw']);
        $this->assertGreaterThanOrEqual(0, $card['srLine']);
    }

    public function testParseSRIgnoresInvalidFormat(): void {
        // Malformed SR tag should not crash
        $content = "## Test\n\nword:::translation\n<!--SR:not-valid-->\n";
        $result = $this->parser->parse($content);

        $card = $result['cards'][0];
        $this->assertEmpty($card['sr']);
    }

    public function testQuickScanCountsDueIncludingToday(): void {
        $today = date('Y-m-d');
        $content = "## Test\n\nword1:::trans1\n<!--SR:!{$today},1,250-->\nword2:::trans2\n<!--SR:!2099-12-31,999,300-->\n";
        $result = $this->parser->quickScan($content);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['due']); // Only the today-due card
        $this->assertEquals(0, $result['new']);
    }

    public function testQuickScanAllNew(): void {
        $content = "## Test\n\nword1:::trans1\n\nword2:::trans2\n\nword3:::trans3\n";
        $result = $this->parser->quickScan($content);

        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['new']);
        $this->assertEquals(0, $result['due']);
    }
}
