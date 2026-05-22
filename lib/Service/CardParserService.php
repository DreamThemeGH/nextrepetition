<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Card Parser Service
 *
 * Parses .md files in Obsidian Spaced Repetition format into structured card arrays.
 * Supports 4 card types from real user data:
 *   1. Basic:  word:::translation
 *   2. Cloze:  sentence with ==word==^[hint]
 *   3. Multi-cloze: sentence with multiple ==word==^[hint] patterns
 *   4. Basic with transcription: word [ IPA ] ::: translation
 *
 * SR metadata format: <!--SR:!YYYY-MM-DD,interval,ease!YYYY-MM-DD,interval,ease-->
 */

namespace OCA\Flashcards\Service;

use Psr\Log\LoggerInterface;

class CardParserService {

    /** Regex for SR metadata tag */
    private const SR_REGEX = '/<!--SR:((?:![^>]+)+)-->/';

    /** Regex for a single SR entry: !date,interval,ease */
    private const SR_ENTRY_REGEX = '/!(\d{4}-\d{2}-\d{2}),(\d+),(\d+)/';

    /** Regex for basic card: front:::back */
    private const BASIC_REGEX = '/^(.+?):::(.+)$/';

    /** Regex for basic card with transcription: word [ IPA ] ::: translation */
    private const TRANSCRIPTION_REGEX = '/^(.+?)\s*\[\s*(.+?)\s*\]\s*:::\s*(.+)$/';

    /** Regex for cloze deletion: ==word==^[hint] or ==word== */
    private const CLOZE_REGEX = '/==([^=]+)==(?:\^\[([^\]]*)\])?/';

    /** Regex for deck tag: #flashcards/path/to/deck */
    private const TAG_REGEX = '/^#flashcards\/(.+)$/m';

    /** Git conflict markers */
    private const CONFLICT_START = '<<<<<<< ';
    private const CONFLICT_MID = '=======';
    private const CONFLICT_END = '>>>>>>> ';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Parse an entire .md file content into a structured deck.
     *
     * @param string $content Raw .md file content
     * @param string $filePath Path for context in error messages
     * @return array{
     *     tag: string,
     *     cards: array<int, array>,
     *     conflicts: array<int, array>,
     *     rawLines: string[],
     *     totalLines: int
     * }
     */
    public function parse(string $content, string $filePath = ''): array {
        $lines = explode("\n", $content);
        $totalLines = count($lines);

        $result = [
            'tag' => '',
            'cards' => [],
            'conflicts' => [],
            'rawLines' => $lines,
            'totalLines' => $totalLines,
        ];

        // Extract deck tag
        if (preg_match(self::TAG_REGEX, $content, $tagMatch)) {
            $result['tag'] = trim($tagMatch[1]);
        }

        // First pass: detect and collect git conflicts
        $conflicts = $this->detectConflicts($lines);
        $result['conflicts'] = $conflicts;

        // Second pass: parse cards line by line
        $currentCard = null;
        $currentCardStartLine = 0;
        $contextLines = [];
        $inConflict = false;

        for ($i = 0; $i < $totalLines; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // Skip empty lines (but track for context)
            if ($trimmed === '') {
                if ($currentCard !== null && !empty($contextLines)) {
                    // Flush context as example block
                    $currentCard['context'][] = $contextLines;
                    $contextLines = [];
                }
                continue;
            }

            // Skip tag lines
            if (str_starts_with($trimmed, '#flashcards/')) {
                continue;
            }

            // Skip git conflict markers
            if (str_starts_with($trimmed, self::CONFLICT_START) ||
                $trimmed === self::CONFLICT_MID ||
                str_starts_with($trimmed, self::CONFLICT_END)) {
                $inConflict = str_starts_with($trimmed, self::CONFLICT_START);
                if ($trimmed === self::CONFLICT_MID || str_starts_with($trimmed, self::CONFLICT_END)) {
                    $inConflict = str_starts_with($trimmed, self::CONFLICT_MID);
                }
                if (str_starts_with($trimmed, self::CONFLICT_END)) {
                    $inConflict = false;
                }
                continue;
            }

            // Check for SR metadata
            if (preg_match(self::SR_REGEX, $trimmed, $srMatch)) {
                if ($currentCard !== null) {
                    $srEntries = $this->parseSREntries($srMatch[1]);
                    
                    // Flush context
                    if (!empty($contextLines)) {
                        $currentCard['context'][] = $contextLines;
                        $contextLines = [];
                    }
                    
                    // For multi-cloze cards: create N cards (one per cloze)
                    if ($currentCard['type'] === 'cloze' && count($currentCard['clozes']) > 1) {
                        foreach ($currentCard['clozes'] as $clozeIndex => $cloze) {
                            $multiCard = $currentCard;
                            $multiCard['clozeIndex'] = $clozeIndex; // Which cloze is hidden
                            $multiCard['totalClozes'] = count($currentCard['clozes']);
                            
                            // Assign SR data for this specific cloze direction
                            if (isset($srEntries[$clozeIndex])) {
                                $multiCard['sr'] = [$srEntries[$clozeIndex]];
                            } else {
                                $multiCard['sr'] = [];
                            }
                            
                            $multiCard['srRaw'] = $srMatch[0];
                            $multiCard['srLine'] = $i;
                            
                            $result['cards'][] = $this->finalizeCard($multiCard);
                        }
                    } else {
                        // Single cloze or basic card
                        $currentCard['sr'] = $srEntries;
                        $currentCard['srRaw'] = $srMatch[0];
                        $currentCard['srLine'] = $i;
                        
                        if ($currentCard['type'] === 'cloze') {
                            $currentCard['clozeIndex'] = 0;
                            $currentCard['totalClozes'] = 1;
                        }
                        
                        $result['cards'][] = $this->finalizeCard($currentCard);
                    }
                    
                    $currentCard = null;
                }
                continue;
            }

            // Try to match card patterns (new card starts)
            $parsed = $this->tryParseCardLine($trimmed, $i);

            if ($parsed !== null) {
                // Finalize previous card if exists (without SR — new/unreviewed card)
                if ($currentCard !== null) {
                    if (!empty($contextLines)) {
                        $currentCard['context'][] = $contextLines;
                        $contextLines = [];
                    }
                    
                    // If cloze card without SR, treat as single cloze (new card)
                    if ($currentCard['type'] === 'cloze') {
                        $currentCard['clozeIndex'] = 0;
                        $currentCard['totalClozes'] = count($currentCard['clozes']);
                    }
                    
                    $result['cards'][] = $this->finalizeCard($currentCard);
                }

                $currentCard = $parsed;
                $currentCardStartLine = $i;
                $contextLines = [];
            } elseif ($currentCard !== null) {
                // Context / translation / example line for current card
                $contextLines[] = $trimmed;
            }
        }

        // Finalize last card if no SR tag followed
        if ($currentCard !== null) {
            if (!empty($contextLines)) {
                $currentCard['context'][] = $contextLines;
            }
            
            // If cloze card without SR, treat as single cloze (new card)
            if ($currentCard['type'] === 'cloze') {
                $currentCard['clozeIndex'] = 0;
                $currentCard['totalClozes'] = count($currentCard['clozes']);
            }
            
            $result['cards'][] = $this->finalizeCard($currentCard);
        }

        // Assign indices
        foreach ($result['cards'] as $idx => &$card) {
            $card['index'] = $idx;
        }
        unset($card);

        return $result;
    }

    /**
     * Try to parse a line as a card definition.
     *
     * @return array|null Raw card data or null if not a card line
     */
    private function tryParseCardLine(string $line, int $lineNum): ?array {
        // Type 1: Basic with transcription: word [ IPA ] ::: translation
        if (preg_match(self::TRANSCRIPTION_REGEX, $line, $m)) {
            return [
                'type' => 'basic',
                'front' => trim($m[1]),
                'transcription' => trim($m[2]),
                'back' => trim($m[3]),
                'line' => $lineNum,
                'rawLine' => $line,
                'sr' => [],
                'srRaw' => '',
                'srLine' => -1,
                'context' => [],
            ];
        }

        // Type 2: Basic card: front:::back (without transcription)
        if (preg_match(self::BASIC_REGEX, $line, $m)) {
            return [
                'type' => 'basic',
                'front' => trim($m[1]),
                'back' => trim($m[2]),
                'line' => $lineNum,
                'rawLine' => $line,
                'sr' => [],
                'srRaw' => '',
                'srLine' => -1,
                'context' => [],
            ];
        }

        // Type 3/4: Cloze card: sentence with ==word==^[hint]
        if (preg_match(self::CLOZE_REGEX, $line)) {
            $clozes = [];
            preg_match_all(self::CLOZE_REGEX, $line, $matches, PREG_SET_ORDER);
            foreach ($matches as $cm) {
                $clozes[] = [
                    'word' => $cm[1],
                    'hint' => $cm[2] ?? '',
                ];
            }

            return [
                'type' => 'cloze',
                'sentence' => $line,
                'clozes' => $clozes,
                'line' => $lineNum,
                'rawLine' => $line,
                'sr' => [],
                'srRaw' => '',
                'srLine' => -1,
                'context' => [],
            ];
        }

        return null;
    }

    /**
     * Parse SR entry string into structured data.
     *
     * @param string $srString e.g. "!2026-06-03,367,366!2026-05-31,364,361"
     * @return array Array of {date, interval, ease}
     */
    private function parseSREntries(string $srString): array {
        $entries = [];
        preg_match_all(self::SR_ENTRY_REGEX, $srString, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $entries[] = [
                'date' => $m[1],
                'interval' => (int)$m[2],
                'ease' => (int)$m[3],
            ];
        }

        return $entries;
    }

    /**
     * Finalize a card — extract translation from context, set defaults.
     */
    private function finalizeCard(array $card): array {
        // For cloze cards, first context line is the translation
        if ($card['type'] === 'cloze' && !empty($card['context'])) {
            $firstCtx = $card['context'][0] ?? [];
            if (!empty($firstCtx)) {
                $card['translation'] = $firstCtx[0] ?? '';
                // Remove first line from context (it's the translation)
                array_shift($card['context'][0]);
                if (empty($card['context'][0])) {
                    array_shift($card['context']);
                }
            }
        }

        // For basic cards, context is examples
        if ($card['type'] === 'basic') {
            $card['examples'] = $card['context'];
        }

        // Determine card state from SR data
        // Note: '2000-01-01' is a dummy date for unreviewed directions
        if (empty($card['sr'])) {
            $card['state'] = 'new';
        } else {
            $today = date('Y-m-d');
            $isDue = false;
            $hasRealSR = false;
            foreach ($card['sr'] as $sr) {
                if ($sr['date'] === '2000-01-01') {
                    continue; // Skip dummy entries
                }
                $hasRealSR = true;
                if ($sr['date'] <= $today) {
                    $isDue = true;
                    break;
                }
            }
            $card['state'] = $hasRealSR ? ($isDue ? 'due' : 'review') : 'new';
        }

        return $card;
    }

    /**
     * Detect git merge conflicts in lines.
     *
     * @return array Array of conflict blocks
     */
    private function detectConflicts(array $lines): array {
        $conflicts = [];
        $current = null;

        for ($i = 0; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            if (str_starts_with($trimmed, self::CONFLICT_START)) {
                $current = [
                    'startLine' => $i,
                    'ours' => '',
                    'theirs' => '',
                    'source' => substr($trimmed, strlen(self::CONFLICT_START)),
                    'phase' => 'ours',
                ];
            } elseif ($trimmed === self::CONFLICT_MID && $current !== null) {
                $current['phase'] = 'theirs';
            } elseif (str_starts_with($trimmed, self::CONFLICT_END) && $current !== null) {
                $current['endLine'] = $i;
                $current['target'] = substr($trimmed, strlen(self::CONFLICT_END));
                unset($current['phase']);
                $conflicts[] = $current;
                $current = null;
            } elseif ($current !== null) {
                if ($current['phase'] === 'ours') {
                    $current['ours'] .= ($current['ours'] ? "\n" : '') . $lines[$i];
                } else {
                    $current['theirs'] .= ($current['theirs'] ? "\n" : '') . $lines[$i];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Quick scan: count cards and due cards in a file without full parse.
     * Used for deck listing to show due counts efficiently.
     *
     * @return array{total: int, due: int, new: int}
     */
    public function quickScan(string $content): array {
        $total = 0;
        $due = 0;
        $new = 0;
        $today = date('Y-m-d');

        $lines = explode("\n", $content);
        $hasCard = false;
        $hasSR = false;
        $cardSRCount = 0; // Total SR entries for this card

        for ($i = 0; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            // Count card definitions (basic or cloze)
            if (preg_match(self::BASIC_REGEX, $trimmed)) {
                // Finalize previous card
                if ($hasCard) {
                    if (!$hasSR) {
                        $total++;
                    } else {
                        // For multi-direction cards: count SR entries as separate cards
                        $total += $cardSRCount;
                    }
                }
                
                $hasCard = true;
                $hasSR = false;
                $cardSRCount = 0;
            } elseif (preg_match(self::CLOZE_REGEX, $trimmed)) {
                // Finalize previous card
                if ($hasCard) {
                    if (!$hasSR) {
                        $total++;
                    } else {
                        // For multi-cloze: count SR entries as separate cards
                        $total += $cardSRCount;
                    }
                }
                
                $hasCard = true;
                $hasSR = false;
                $cardSRCount = 0;
            }

            // Check SR entries
            if (preg_match(self::SR_REGEX, $trimmed, $srMatch)) {
                $hasSR = true;
                $entries = [];
                preg_match_all(self::SR_ENTRY_REGEX, $srMatch[1], $entries, PREG_SET_ORDER);

                $cardSRCount = count($entries);
                $hasRealEntry = false;

                foreach ($entries as $entry) {
                    if ($entry[1] === '2000-01-01') {
                        continue; // Skip dummy date for unreviewed direction
                    }
                    $hasRealEntry = true;
                }
                
                // If all entries are dummy, treat as new card
                if (!$hasRealEntry) {
                    $hasSR = false;
                }
            }
        }

        // Finalize last card
        if ($hasCard) {
            if (!$hasSR) {
                $total++;
            } else {
                $total += $cardSRCount;
            }
        }

        // Keep deck-list due/new counters aligned with real study queue logic.
        // This mirrors BufferService::getDueCards direction handling.
        $parsed = $this->parse($content);
        foreach ($parsed['cards'] as $card) {
            if (($card['state'] ?? '') === 'new') {
                $new++;
                continue;
            }

            if (($card['state'] ?? '') !== 'due') {
                continue;
            }

            $dueDirections = 0;
            if (isset($card['sr']) && is_array($card['sr'])) {
                foreach ($card['sr'] as $sr) {
                    if (isset($sr['date']) && $sr['date'] !== '2000-01-01' && $sr['date'] <= $today) {
                        $dueDirections++;
                    }
                }
            }

            // Fallback parity with BufferService for edge cases.
            if ($dueDirections === 0) {
                $dueDirections = 1;
            }

            $due += $dueDirections;
        }

        return [
            'total' => $total,
            'due' => $due,
            'new' => $new,
        ];
    }
}
