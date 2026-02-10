<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Conflict Resolver
 *
 * Handles git merge conflicts found in .md files.
 * Real user files contain conflicts like:
 *
 * <<<<<<< HEAD
 * <!--SR:!2025-10-27,208,346!2026-03-22,283,368-->
 * =======
 * <!--SR:!2025-10-27,208,346!2026-03-05,277,368-->
 * >>>>>>> origin/main
 *
 * Strategy: pick the SR entry with the larger interval (user progressed further).
 */

namespace OCA\Flashcards\Service;

use Psr\Log\LoggerInterface;

class ConflictResolver {

    private const SR_REGEX = '/<!--SR:((?:![^>]+)+)-->/';
    private const SR_ENTRY_REGEX = '/!(\d{4}-\d{2}-\d{2}),(\d+),(\d+)/';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve all git conflicts in file content.
     *
     * @param string $content File content with potential conflicts
     * @param string $strategy 'auto' = pick best SR, 'ours' = HEAD, 'theirs' = origin
     * @return array{content: string, resolved: int}
     */
    public function resolve(string $content, string $strategy = 'auto'): array {
        $resolved = 0;
        $lines = explode("\n", $content);
        $result = [];

        $inConflict = false;
        $ours = '';
        $theirs = '';
        $phase = '';

        for ($i = 0; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            if (str_starts_with($trimmed, '<<<<<<< ')) {
                $inConflict = true;
                $phase = 'ours';
                $ours = '';
                $theirs = '';
                continue;
            }

            if ($trimmed === '=======' && $inConflict) {
                $phase = 'theirs';
                continue;
            }

            if (str_starts_with($trimmed, '>>>>>>> ') && $inConflict) {
                // Resolve this conflict
                $chosen = $this->resolveConflict($ours, $theirs, $strategy);
                if (!empty(trim($chosen))) {
                    $result[] = $chosen;
                }
                $inConflict = false;
                $resolved++;
                continue;
            }

            if ($inConflict) {
                if ($phase === 'ours') {
                    $ours .= ($ours ? "\n" : '') . $lines[$i];
                } else {
                    $theirs .= ($theirs ? "\n" : '') . $lines[$i];
                }
            } else {
                $result[] = $lines[$i];
            }
        }

        return [
            'content' => implode("\n", $result),
            'resolved' => $resolved,
        ];
    }

    /**
     * Resolve a single conflict block.
     */
    private function resolveConflict(string $ours, string $theirs, string $strategy): string {
        if ($strategy === 'ours') {
            return $ours;
        }
        if ($strategy === 'theirs') {
            return $theirs;
        }

        // Auto strategy: compare SR metadata, pick the one with larger total interval
        $oursScore = $this->scoreSR($ours);
        $theirsScore = $this->scoreSR($theirs);

        if ($oursScore >= $theirsScore) {
            return $ours;
        }
        return $theirs;
    }

    /**
     * Score SR content by total interval (higher = user learned more).
     */
    private function scoreSR(string $content): int {
        $score = 0;
        if (preg_match(self::SR_REGEX, $content, $m)) {
            preg_match_all(self::SR_ENTRY_REGEX, $m[1], $entries, PREG_SET_ORDER);
            foreach ($entries as $entry) {
                $score += (int)$entry[2]; // sum of intervals
            }
        }
        return $score;
    }

    /**
     * Check if content has unresolved conflicts.
     */
    public function hasConflicts(string $content): bool {
        return str_contains($content, '<<<<<<< ');
    }

    /**
     * Count unresolved conflicts.
     */
    public function countConflicts(string $content): int {
        return substr_count($content, '<<<<<<< ');
    }
}
