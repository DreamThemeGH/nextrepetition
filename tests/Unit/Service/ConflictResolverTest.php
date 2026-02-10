<?php

declare(strict_types=1);

namespace OCA\Flashcards\Tests\Unit\Service;

use OCA\Flashcards\Service\ConflictResolver;
use PHPUnit\Framework\TestCase;

class ConflictResolverTest extends TestCase {
    private ConflictResolver $resolver;

    protected function setUp(): void {
        $this->resolver = new ConflictResolver();
    }

    public function testHasConflicts(): void {
        $content = "word:::trans\n<<<<<<< HEAD\n<!--SR:!2025-06-15,10,270-->\n=======\n<!--SR:!2025-06-14,8,260-->\n>>>>>>> abc123\n";
        $this->assertTrue($this->resolver->hasConflicts($content));
    }

    public function testNoConflicts(): void {
        $content = "word:::trans\n<!--SR:!2025-06-15,10,270-->\n";
        $this->assertFalse($this->resolver->hasConflicts($content));
    }

    public function testCountConflicts(): void {
        $content = "<<<<<<< HEAD\nline1\n=======\nline2\n>>>>>>> abc\n\n<<<<<<< HEAD\nline3\n=======\nline4\n>>>>>>> def\n";
        $this->assertEquals(2, $this->resolver->countConflicts($content));
    }

    public function testAutoResolvePrefersLargerInterval(): void {
        $content = "word:::trans\n<<<<<<< HEAD\n<!--SR:!2025-06-15,10,270-->\n=======\n<!--SR:!2025-06-20,15,280-->\n>>>>>>> abc123\n";
        $resolved = $this->resolver->resolve($content, 'auto');

        $this->assertStringNotContainsString('<<<<<<<', $resolved);
        $this->assertStringNotContainsString('>>>>>>>', $resolved);
        // Should pick the one with interval 15 (larger)
        $this->assertStringContainsString('15', $resolved);
    }

    public function testResolveOurs(): void {
        $content = "<<<<<<< HEAD\nours line\n=======\ntheirs line\n>>>>>>> abc\n";
        $resolved = $this->resolver->resolve($content, 'ours');

        $this->assertStringContainsString('ours line', $resolved);
        $this->assertStringNotContainsString('theirs line', $resolved);
    }

    public function testResolveTheirs(): void {
        $content = "<<<<<<< HEAD\nours line\n=======\ntheirs line\n>>>>>>> abc\n";
        $resolved = $this->resolver->resolve($content, 'theirs');

        $this->assertStringContainsString('theirs line', $resolved);
        $this->assertStringNotContainsString('ours line', $resolved);
    }

    public function testResolvePreservesNonConflictContent(): void {
        $content = "## Heading\n\nword:::trans\n<<<<<<< HEAD\n<!--SR:!2025-06-15,10,270-->\n=======\n<!--SR:!2025-06-20,15,280-->\n>>>>>>> abc\n\nother:::content\n";
        $resolved = $this->resolver->resolve($content, 'auto');

        $this->assertStringContainsString('## Heading', $resolved);
        $this->assertStringContainsString('word:::trans', $resolved);
        $this->assertStringContainsString('other:::content', $resolved);
    }
}
