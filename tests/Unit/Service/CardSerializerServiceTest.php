<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Card Serializer Service Unit Tests
 *
 * Tests the CardSerializerService that writes updated SR metadata
 * back to .md files without altering user formatting.
 */

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\CardSerializerService;
use OCA\Flashcards\Service\CardParserService;
use PHPUnit\Framework\TestCase;

class CardSerializerServiceTest extends TestCase {
    private CardSerializerService $serializer;
    private CardParserService $parser;

    protected function setUp(): void {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->serializer = new CardSerializerService($logger);
        $this->parser = new CardParserService($logger);
    }

    // ========================================================================
    // Round-trip: parse → serialize → re-parse (no changes)
    // ========================================================================

    public function testRoundTripBasicCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);
        $this->assertStringContainsString('word:::translation', $serialized);
    }

    public function testRoundTripWithSRPreserved(): void {
        $content = "## Words\n\nword:::translation\n<!--SR:!2026-06-05,15,270-->\n";
        $parsed = $this->parser->parse($content);

        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);
        $reparsed = $this->parser->parse($serialized);

        $this->assertCount(1, $reparsed['cards']);
        $this->assertCount(1, $reparsed['cards'][0]['sr']);
        $this->assertEquals(15, $reparsed['cards'][0]['sr'][0]['interval']);
        $this->assertEquals(270, $reparsed['cards'][0]['sr'][0]['ease']);
    }

    public function testRoundTripClozeCard(): void {
        $content = "## Cloze\n\nI ==like==^[люблю] pizza\nпицца\n<!--SR:!2026-06-03,3,260-->\n";
        $parsed = $this->parser->parse($content);
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('I ==like==^[люблю] pizza', $serialized);
        $this->assertStringContainsString('пицца', $serialized);
        $this->assertStringContainsString('<!--SR:!2026-06-03,3,260-->', $serialized);
    }

    // ========================================================================
    // SR update
    // ========================================================================

    public function testSRUpdatePreservesStructure(): void {
        $content = "## Words\n\nword:::translation\n<!--SR:!2025-06-01,5,250-->\n\nother:::stuff\n";
        $parsed = $this->parser->parse($content);

        // Update SR on first card
        $parsed['cards'][0]['sr'] = [['date' => '2025-06-20', 'interval' => 15, 'ease' => 270]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('<!--SR:!2025-06-20,15,270-->', $serialized);
        $this->assertStringContainsString('other:::stuff', $serialized);
    }

    public function testSRUpdatePreservesUserContent(): void {
        // After SR update, the original card text should be unchanged
        $content = "## Words\n\nhello:::привет\n<!--SR:!2025-06-01,5,250-->\n\nworld:::мир\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [['date' => '2025-06-20', 'interval' => 15, 'ease' => 270]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('hello:::привет', $serialized);
        $this->assertStringContainsString('world:::мир', $serialized);
    }

    // ========================================================================
    // Insert SR for previously unreviewed card
    // ========================================================================

    public function testAddSRToNewCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [['date' => '2025-06-15', 'interval' => 1, 'ease' => 250]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('word:::translation', $serialized);
        $this->assertStringContainsString('<!--SR:!2025-06-15,1,250-->', $serialized);
    }

    public function testAddSRToNewCardReparseable(): void {
        // After adding SR to a new card, the result should be parseable
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [['date' => '2025-06-15', 'interval' => 5, 'ease' => 260]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        // Re-parse and verify
        $reparsed = $this->parser->parse($serialized);
        $this->assertCount(1, $reparsed['cards']);
        $this->assertEquals(5, $reparsed['cards'][0]['sr'][0]['interval']);
        $this->assertEquals(260, $reparsed['cards'][0]['sr'][0]['ease']);
        $this->assertEquals('2025-06-15', $reparsed['cards'][0]['sr'][0]['date']);
    }

    // ========================================================================
    // Dual SR serialization
    // ========================================================================

    public function testDualSRSerialization(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [
            ['date' => '2025-06-15', 'interval' => 1, 'ease' => 250],
            ['date' => '2025-06-10', 'interval' => 3, 'ease' => 260],
        ];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('<!--SR:!2025-06-15,1,250!2025-06-10,3,260-->', $serialized);
    }

    public function testDualSRRoundTrip(): void {
        $content = "## Words\n\nword:::translation\n<!--SR:!2026-06-05,10,250!2026-05-31,5,260-->\n";
        $parsed = $this->parser->parse($content);
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);
        $reparsed = $this->parser->parse($serialized);

        $card = $reparsed['cards'][0];
        $this->assertCount(2, $card['sr']);
        $this->assertEquals(10, $card['sr'][0]['interval']);
        $this->assertEquals(5, $card['sr'][1]['interval']);
    }

    // ========================================================================
    // buildSRString
    // ========================================================================

    public function testBuildSRStringSingle(): void {
        $srString = $this->serializer->buildSRString([
            ['date' => '2026-06-05', 'interval' => 15, 'ease' => 270],
        ]);
        $this->assertEquals('<!--SR:!2026-06-05,15,270-->', $srString);
    }

    public function testBuildSRStringDual(): void {
        $srString = $this->serializer->buildSRString([
            ['date' => '2026-06-05', 'interval' => 15, 'ease' => 270],
            ['date' => '2026-05-31', 'interval' => 10, 'ease' => 260],
        ]);
        $this->assertEquals('<!--SR:!2026-06-05,15,270!2026-05-31,10,260-->', $srString);
    }

    public function testBuildSRStringDummy(): void {
        $srString = $this->serializer->buildSRString([
            ['date' => '2000-01-01', 'interval' => 1, 'ease' => 250],
        ]);
        $this->assertEquals('<!--SR:!2000-01-01,1,250-->', $srString);
    }

    // ========================================================================
    // Add card
    // ========================================================================

    public function testAddBasicCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $newContent = $this->serializer->addCard($content, [
            'type' => 'basic',
            'front' => 'new',
            'back' => 'новый',
        ]);

        $this->assertStringContainsString('new:::новый', $newContent);
        $this->assertStringContainsString('word:::translation', $newContent);
    }

    public function testAddClozeCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $newContent = $this->serializer->addCard($content, [
            'type' => 'cloze',
            'sentence' => 'I ==like== pizza',
        ]);

        $this->assertStringContainsString('I ==like== pizza', $newContent);
    }

    public function testAddCardWithTranscription(): void {
        $content = "## Words\n\nword:::translation\n";
        $newContent = $this->serializer->addCard($content, [
            'type' => 'basic',
            'front' => 'hello',
            'transcription' => 'həˈloʊ',
            'back' => 'привет',
        ]);

        $this->assertStringContainsString('hello [ həˈloʊ ]:::привет', $newContent);
    }

    // ========================================================================
    // Remove card
    // ========================================================================

    public function testRemoveCard(): void {
        $content = "## Words\n\nword1:::trans1\nword2:::trans2\n";
        $parsed = $this->parser->parse($content);

        $newContent = $this->serializer->removeCard($parsed, 0);
        $this->assertStringNotContainsString('word1:::trans1', $newContent);
        $this->assertStringContainsString('word2:::trans2', $newContent);
    }

    public function testRemoveCardWithSR(): void {
        $content = "## Words\n\nword1:::trans1\n<!--SR:!2026-06-05,10,250-->\nword2:::trans2\n";
        $parsed = $this->parser->parse($content);

        $newContent = $this->serializer->removeCard($parsed, 0);
        $this->assertStringNotContainsString('word1:::trans1', $newContent);
        $this->assertStringNotContainsString('<!--SR:!2026-06-05,10,250-->', $newContent);
        $this->assertStringContainsString('word2:::trans2', $newContent);
    }

    // ========================================================================
    // Clear SR metadata
    // ========================================================================

    public function testClearSRMetadata(): void {
        $content = "## Words\n\nword:::translation\n<!--SR:!2026-06-05,15,270-->\n\nother:::stuff\n<!--SR:!2026-06-01,5,250-->\n";
        $parsed = $this->parser->parse($content);

        $cleared = $this->serializer->clearSRMetadata($parsed);
        $this->assertStringNotContainsString('<!--SR:', $cleared);
        $this->assertStringContainsString('word:::translation', $cleared);
        $this->assertStringContainsString('other:::stuff', $cleared);
    }

    // ========================================================================
    // Multiple cards update
    // ========================================================================

    public function testUpdateMultipleCardsInOneFile(): void {
        $content = "## Words\n\nword1:::trans1\n<!--SR:!2026-06-01,5,250-->\n\nword2:::trans2\n<!--SR:!2026-06-03,8,260-->\n";
        $parsed = $this->parser->parse($content);

        // Update both cards
        $parsed['cards'][0]['sr'] = [['date' => '2026-07-01', 'interval' => 20, 'ease' => 270]];
        $parsed['cards'][1]['sr'] = [['date' => '2026-07-15', 'interval' => 30, 'ease' => 280]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('word1:::trans1', $serialized);
        $this->assertStringContainsString('word2:::trans2', $serialized);
        $this->assertStringContainsString('<!--SR:!2026-07-01,20,270-->', $serialized);
        $this->assertStringContainsString('<!--SR:!2026-07-15,30,280-->', $serialized);

        // Re-parse to verify integrity
        $reparsed = $this->parser->parse($serialized);
        $this->assertCount(2, $reparsed['cards']);
        $this->assertEquals(20, $reparsed['cards'][0]['sr'][0]['interval']);
        $this->assertEquals(30, $reparsed['cards'][1]['sr'][0]['interval']);
    }

    public function testUpdateCardWithoutChangingSRPreservesOriginal(): void {
        // If a card has no SR updates, the original SR line should be preserved
        $content = "## Words\n\nword1:::trans1\n<!--SR:!2026-06-01,5,250-->\nword2:::trans2\n<!--SR:!2026-06-03,8,260-->\n";
        $parsed = $this->parser->parse($content);

        // Only update second card
        $parsed['cards'][1]['sr'] = [['date' => '2026-07-15', 'interval' => 30, 'ease' => 280]];
        $serialized = $this->serializer->serialize($parsed, $parsed['cards']);

        $this->assertStringContainsString('<!--SR:!2026-06-01,5,250-->', $serialized);
        $this->assertStringContainsString('<!--SR:!2026-07-15,30,280-->', $serialized);
    }
}
