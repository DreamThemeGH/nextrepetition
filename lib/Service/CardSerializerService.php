<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Card Serializer Service
 *
 * Serializes card data back to .md format.
 * CRITICAL: Only SR tags are updated. All user formatting is preserved exactly.
 *
 * Strategy:
 *   1. Keep original rawLines array
 *   2. For each card that was reviewed, update ONLY its <!--SR:--> line
 *   3. If card had no SR tag, insert a new one after the card line
 *   4. If conflicts were resolved, remove conflict markers
 */

namespace OCA\Flashcards\Service;

use Psr\Log\LoggerInterface;

class CardSerializerService {

    /** Regex for SR metadata tag */
    private const SR_REGEX = '/<!--SR:((?:![^>]+)+)-->/';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Serialize updated cards back to .md file content.
     *
     * @param array $parseResult Original parse result from CardParserService
     * @param array $updatedCards Array of cards with potentially updated SR data
     * @return string Updated .md file content
     */
    public function serialize(array $parseResult, array $updatedCards): string {
        $lines = $parseResult['rawLines'];

        // Build a map of line number → updated SR string
        $srUpdates = [];   // lineNum => new SR string
        $srInserts = [];   // lineNum => insert SR after this line

        foreach ($updatedCards as $card) {
            if (empty($card['sr'])) {
                continue; // No SR data to write
            }

            $srString = $this->buildSRString($card['sr']);

            if (isset($card['srLine']) && $card['srLine'] >= 0) {
                // Update existing SR line
                $srUpdates[$card['srLine']] = $srString;
            } elseif (isset($card['line'])) {
                // Insert new SR after the card line (or after context/translation)
                $insertAfter = $this->findInsertPosition($card, $lines);
                $srInserts[$insertAfter] = $srString;
            }
        }

        // Apply updates to lines (in reverse order to keep line numbers valid)
        foreach ($srUpdates as $lineNum => $srString) {
            if (isset($lines[$lineNum])) {
                // Replace SR tag in the line, preserving any surrounding content
                $lines[$lineNum] = preg_replace(self::SR_REGEX, $srString, $lines[$lineNum]);
            }
        }

        // Apply inserts (sort descending to preserve line numbers)
        krsort($srInserts);
        foreach ($srInserts as $afterLine => $srString) {
            array_splice($lines, $afterLine + 1, 0, [$srString]);
        }

        return implode("\n", $lines);
    }

    /**
     * Build an SR metadata string from SR entries.
     *
     * @param array $srEntries Array of {date, interval, ease}
     * @return string e.g. "<!--SR:!2026-06-03,367,366!2026-05-31,364,361-->"
     */
    public function buildSRString(array $srEntries): string {
        $parts = [];
        foreach ($srEntries as $entry) {
            $parts[] = sprintf(
                '!%s,%d,%d',
                $entry['date'],
                $entry['interval'],
                $entry['ease'],
            );
        }
        return '<!--SR:' . implode('', $parts) . '-->';
    }

    /**
     * Find the best line to insert SR tag after.
     * For basic cards: after the card line
     * For cloze cards: after the translation line
     */
    private function findInsertPosition(array $card, array $lines): int {
        $cardLine = $card['line'];

        // Look forward from card line to find the right position
        // Skip non-empty lines that are context/translation until we hit
        // an empty line, another card, or end of file
        $pos = $cardLine;
        $totalLines = count($lines);

        for ($i = $cardLine + 1; $i < $totalLines; $i++) {
            $trimmed = trim($lines[$i]);

            // Empty line = end of this card's block
            if ($trimmed === '') {
                break;
            }

            // Another card starting = stop before it
            if (str_contains($trimmed, ':::') || preg_match('/==.+==/', $trimmed)) {
                break;
            }

            // SR tag of another card
            if (preg_match(self::SR_REGEX, $trimmed)) {
                break;
            }

            $pos = $i;
        }

        return $pos;
    }

    /**
     * Add a new card to the file content.
     *
     * @param string $content Current file content
     * @param array $cardData Card data to add
     * @return string Updated content
     */
    public function addCard(string $content, array $cardData): string {
        $newLines = [];

        if ($cardData['type'] === 'basic') {
            $front = $cardData['front'];
            if (!empty($cardData['transcription'])) {
                $front .= ' [ ' . $cardData['transcription'] . ' ]';
            }
            $newLines[] = $front . ':::' . $cardData['back'];
        } elseif ($cardData['type'] === 'cloze') {
            $newLines[] = $cardData['sentence'];
            if (!empty($cardData['translation'])) {
                $newLines[] = $cardData['translation'];
            }
        }

        // Add examples if present
        if (!empty($cardData['examples'])) {
            foreach ($cardData['examples'] as $example) {
                $newLines[] = '';
                if (is_array($example)) {
                    foreach ($example as $exLine) {
                        $newLines[] = $exLine;
                    }
                } else {
                    $newLines[] = $example;
                }
            }
        }

        $content = rtrim($content);
        return $content . "\n\n" . implode("\n", $newLines) . "\n";
    }

    /**
     * Remove a card from the file content by index.
     *
     * @param array $parseResult Parse result from CardParserService
     * @param int $cardIndex Index of card to remove
     * @return string Updated content
     */
    public function removeCard(array $parseResult, int $cardIndex): string {
        $lines = $parseResult['rawLines'];
        $cards = $parseResult['cards'];

        if (!isset($cards[$cardIndex])) {
            return implode("\n", $lines);
        }

        $card = $cards[$cardIndex];
        $startLine = $card['line'];

        // Find end line (start of next card or SR line + 1)
        $endLine = $card['srLine'] >= 0 ? $card['srLine'] : $startLine;

        // Look for next card to determine range
        if (isset($cards[$cardIndex + 1])) {
            $nextCardLine = $cards[$cardIndex + 1]['line'];
            // Remove up to (but not including) next card line
            // Also remove any blank lines between
            $endLine = max($endLine, $nextCardLine - 1);
            while ($endLine > $startLine && trim($lines[$endLine]) === '') {
                $endLine--;
            }
        } else {
            // Last card — extend to its SR line
            $endLine = max($endLine, $startLine);
        }

        // Remove lines
        array_splice($lines, $startLine, $endLine - $startLine + 1);

        // Clean up double blank lines
        $content = implode("\n", $lines);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return $content;
    }

    public function clearSRMetadata(array $parseResult): string {
        $lines = [];

        foreach ($parseResult['rawLines'] as $line) {
            $updatedLine = preg_replace(self::SR_REGEX, '', $line);
            if ($updatedLine === null) {
                $updatedLine = $line;
            }

            if (trim($updatedLine) === '') {
                if (preg_match(self::SR_REGEX, trim($line)) === 1) {
                    continue;
                }
            }

            $lines[] = rtrim($updatedLine);
        }

        return implode("\n", $lines);
    }

    /**
     * Update a card's content (front/back text) in the file.
     *
     * @param array $parseResult Parse result
     * @param int $cardIndex Card index
     * @param array $newData New card data
     * @return string Updated content
     */
    public function updateCard(array $parseResult, int $cardIndex, array $newData): string {
        $lines = $parseResult['rawLines'];
        $cards = $parseResult['cards'];

        if (!isset($cards[$cardIndex])) {
            return implode("\n", $lines);
        }

        $card = $cards[$cardIndex];
        $lineNum = $card['line'];

        // Rebuild the card line
        if ($card['type'] === 'basic') {
            $front = $newData['front'] ?? $card['front'];
            $back = $newData['back'] ?? $card['back'];
            $transcription = $newData['transcription'] ?? ($card['transcription'] ?? '');

            if (!empty($transcription)) {
                $lines[$lineNum] = $front . ' [ ' . $transcription . ' ] ::: ' . $back;
            } else {
                $lines[$lineNum] = $front . ':::' . $back;
            }
        } elseif ($card['type'] === 'cloze') {
            if (isset($newData['sentence'])) {
                $lines[$lineNum] = $newData['sentence'];
            }
            // Update translation line (line after sentence, if it exists and is not SR/card/empty)
            if (isset($newData['translation'])) {
                $translationLine = $lineNum + 1;
                if ($translationLine < count($lines)) {
                    $nextTrimmed = trim($lines[$translationLine]);
                    $isCard = str_contains($nextTrimmed, ':::') || preg_match('/==.+==/', $nextTrimmed);
                    $isSR = preg_match(self::SR_REGEX, $nextTrimmed);

                    if (!empty($card['translation']) && !$isCard && !$isSR && $nextTrimmed !== '') {
                        // Replace existing translation line
                        $lines[$translationLine] = $newData['translation'];
                    } elseif (!empty($newData['translation'])) {
                        // Insert new translation line after sentence
                        array_splice($lines, $translationLine, 0, [$newData['translation']]);
                    }
                } elseif (!empty($newData['translation'])) {
                    // Append translation at end of file
                    $lines[] = $newData['translation'];
                }
            }
        }

        return implode("\n", $lines);
    }
}
