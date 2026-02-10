<?php

declare(strict_types=1);

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\CardSerializerService;
use OCA\Flashcards\Service\CardParserService;
use PHPUnit\Framework\TestCase;

class CardSerializerServiceTest extends TestCase {
    private CardSerializerService $serializer;
    private CardParserService $parser;

    protected function setUp(): void {
        $this->serializer = new CardSerializerService();
        $this->parser = new CardParserService();
    }

    public function testRoundTripBasicCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $serialized = $this->serializer->serialize($parsed['rawLines'], $parsed['cards']);
        $this->assertStringContainsString('word:::translation', $serialized);
    }

    public function testSRUpdatePreservesStructure(): void {
        $content = "## Words\n\nword:::translation\n<!--SR:!2025-06-01,5,250-->\n\nother:::stuff\n";
        $parsed = $this->parser->parse($content);

        // Update SR on first card
        $parsed['cards'][0]['sr'] = [['date' => '2025-06-20', 'interval' => 15, 'ease' => 270]];
        $serialized = $this->serializer->serialize($parsed['rawLines'], $parsed['cards']);

        $this->assertStringContainsString('<!--SR:!2025-06-20,15,270-->', $serialized);
        $this->assertStringContainsString('other:::stuff', $serialized);
    }

    public function testAddSRToNewCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [['date' => '2025-06-15', 'interval' => 1, 'ease' => 250]];
        $serialized = $this->serializer->serialize($parsed['rawLines'], $parsed['cards']);

        $this->assertStringContainsString('word:::translation', $serialized);
        $this->assertStringContainsString('<!--SR:!2025-06-15,1,250-->', $serialized);
    }

    public function testDualSRSerialization(): void {
        $content = "## Words\n\nword:::translation\n";
        $parsed = $this->parser->parse($content);

        $parsed['cards'][0]['sr'] = [
            ['date' => '2025-06-15', 'interval' => 1, 'ease' => 250],
            ['date' => '2025-06-10', 'interval' => 3, 'ease' => 260],
        ];
        $serialized = $this->serializer->serialize($parsed['rawLines'], $parsed['cards']);

        $this->assertStringContainsString('<!--SR:!2025-06-15,1,250!2025-06-10,3,260-->', $serialized);
    }

    public function testAddBasicCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $newContent = $this->serializer->addCard($content, 'basic', [
            'front' => 'new',
            'back' => 'новый',
        ]);

        $this->assertStringContainsString('new:::новый', $newContent);
        // Original card preserved
        $this->assertStringContainsString('word:::translation', $newContent);
    }

    public function testAddClozeCard(): void {
        $content = "## Words\n\nword:::translation\n";
        $newContent = $this->serializer->addCard($content, 'cloze', [
            'sentence' => 'I ==like== pizza',
        ]);

        $this->assertStringContainsString('I ==like== pizza', $newContent);
    }

    public function testRemoveCard(): void {
        $content = "## Words\n\nword1:::trans1\nword2:::trans2\n";
        $parsed = $this->parser->parse($content);

        $newContent = $this->serializer->removeCard($parsed['rawLines'], 0);
        $this->assertStringNotContainsString('word1:::trans1', $newContent);
        $this->assertStringContainsString('word2:::trans2', $newContent);
    }
}
