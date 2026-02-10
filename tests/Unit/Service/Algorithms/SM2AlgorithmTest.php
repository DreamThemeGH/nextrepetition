<?php

declare(strict_types=1);

namespace OCA\Flashcards\Tests\Unit\Service\Algorithms;

use OCA\Flashcards\Service\Algorithms\SM2Algorithm;
use PHPUnit\Framework\TestCase;

class SM2AlgorithmTest extends TestCase {
    private SM2Algorithm $sm2;

    protected function setUp(): void {
        $this->sm2 = new SM2Algorithm();
    }

    public function testFirstReviewPerfect(): void {
        $result = $this->sm2->calculateNextReview(4, 0, 2.5);
        $this->assertEquals(1, $result['interval']);
        $this->assertGreaterThanOrEqual(2.5, $result['ease']);
    }

    public function testSecondReviewGood(): void {
        $result = $this->sm2->calculateNextReview(3, 1, 2.5);
        $this->assertEquals(6, $result['interval']);
    }

    public function testThirdReviewGood(): void {
        $result = $this->sm2->calculateNextReview(3, 6, 2.5);
        $this->assertEquals(15, $result['interval']); // 6 * 2.5 = 15
    }

    public function testAgainResetsInterval(): void {
        $result = $this->sm2->calculateNextReview(0, 30, 2.5);
        $this->assertEquals(1, $result['interval']);
    }

    public function testHardReducesEase(): void {
        $initial = 2.5;
        $result = $this->sm2->calculateNextReview(1, 10, $initial);
        $this->assertLessThan($initial, $result['ease']);
    }

    public function testEaseNeverBelowMinimum(): void {
        $result = $this->sm2->calculateNextReview(0, 1, 1.3);
        $this->assertGreaterThanOrEqual(1.3, $result['ease']);
    }

    public function testPerfectIncreasesEase(): void {
        $initial = 2.5;
        $result = $this->sm2->calculateNextReview(4, 10, $initial);
        $this->assertGreaterThan($initial, $result['ease']);
    }

    public function testPredictIntervals(): void {
        $predictions = $this->sm2->predictIntervals(10, 2.5);
        $this->assertCount(5, $predictions);
        // Rating 0 should give interval 1
        $this->assertEquals(1, $predictions[0]['interval']);
        // Higher ratings = higher intervals
        $this->assertLessThanOrEqual($predictions[4]['interval'], $predictions[4]['interval']);
    }

    public function testFormatInterval(): void {
        $this->assertEquals('1d', $this->sm2->formatInterval(1));
        $this->assertEquals('7d', $this->sm2->formatInterval(7));
        $this->assertEquals('2.0mo', $this->sm2->formatInterval(60));
        $this->assertEquals('1.0yr', $this->sm2->formatInterval(365));
    }
}
