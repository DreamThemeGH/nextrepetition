<?php

declare(strict_types=1);

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\CardParserService;
use PHPUnit\Framework\TestCase;

class CardParserServiceTest extends TestCase {
    private CardParserService $parser;

    protected function setUp(): void {
        $this->parser = new CardParserService();
    }

    public function testParseBasicCard(): void {
        $content = "## Tag1\n\nword:::translation\n";
        $result = $this->parser->parse($content);

        $this->assertEquals('Tag1', $result['tag']);
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
}
