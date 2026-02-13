#!/usr/bin/env php
<?php
/**
 * Migration: Reset cards with intervals > 3.5 years (1277 days)
 * 
 * This fixes cards corrupted by the old estimateRepetitions bug.
 * 
 * Strategy:
 * - If interval > 1277 days AND ease > 300: CORRUPTED by old bug
 *   → Reset to interval=30, ease=250 (moderate review state)
 * - If interval > 1277 days but ease <= 300: User really knows it well
 *   → Cap to interval=365 (1 year max), keep ease
 * 
 * Usage:
 *   php migrate-reset-large-intervals.php /path/to/file.md [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);
$filePath = $argv[1] ?? null;

if (!$filePath || !file_exists($filePath)) {
    echo "Usage: php migrate-reset-large-intervals.php <file.md> [--dry-run]\n";
    exit(1);
}

const MAX_SAFE_INTERVAL = 1277; // 3.5 years
const MAX_CAPPED_INTERVAL = 365; // 1 year cap
const RESET_INTERVAL = 30; // Reset to 1 month
const RESET_EASE = 250; // 2.5x
const CORRUPTION_EASE_THRESHOLD = 300; // If ease > 3.0, likely corrupted

$content = file_get_contents($filePath);
$modified = false;
$stats = [
    'total_sr_tags' => 0,
    'corrupted_reset' => 0,
    'capped' => 0,
    'unchanged' => 0,
];

// Find all SR tags: <!--SR:!date,interval,ease!date,interval,ease-->
$content = preg_replace_callback(
    '/<!--SR:((?:![^!>]+)+)-->/',
    function ($matches) use (&$modified, &$stats, $dryRun) {
        $stats['total_sr_tags']++;
        $srTag = $matches[1];
        $entries = explode('!', trim($srTag, '!'));
        
        $changed = false;
        $newEntries = [];
        
        foreach ($entries as $entry) {
            $parts = explode(',', $entry);
            if (count($parts) !== 3) {
                $newEntries[] = $entry;
                continue;
            }
            
            [$date, $interval, $ease] = $parts;
            $interval = (int)$interval;
            $ease = (int)$ease;
            
            // Skip dummy dates
            if ($date === '2000-01-01') {
                $newEntries[] = $entry;
                continue;
            }
            
            if ($interval > MAX_SAFE_INTERVAL) {
                // Corrupted by old bug: high interval + high ease
                if ($ease > CORRUPTION_EASE_THRESHOLD) {
                    echo "  🔧 RESET: interval={$interval}d, ease={$ease} → interval=" . RESET_INTERVAL . "d, ease=" . RESET_EASE . "\n";
                    $interval = RESET_INTERVAL;
                    $ease = RESET_EASE;
                    // Recalculate date
                    $newDate = date('Y-m-d', strtotime('+' . $interval . ' days'));
                    $newEntries[] = "{$newDate},{$interval},{$ease}";
                    $stats['corrupted_reset']++;
                    $changed = true;
                } else {
                    // High interval but normal ease - user really knows it
                    echo "  📌 CAP: interval={$interval}d, ease={$ease} → interval=" . MAX_CAPPED_INTERVAL . "d, ease={$ease}\n";
                    $interval = MAX_CAPPED_INTERVAL;
                    // Recalculate date
                    $newDate = date('Y-m-d', strtotime('+' . $interval . ' days'));
                    $newEntries[] = "{$newDate},{$interval},{$ease}";
                    $stats['capped']++;
                    $changed = true;
                }
            } else {
                $stats['unchanged']++;
                $newEntries[] = $entry;
            }
        }
        
        if ($changed) {
            $modified = true;
            return '<!--SR:!' . implode('!', $newEntries) . '-->';
        }
        
        return $matches[0];
    },
    $content
);

// Report
echo "\n📊 Statistics for: " . basename($filePath) . "\n";
echo "  Total SR tags: {$stats['total_sr_tags']}\n";
echo "  Corrupted (reset): {$stats['corrupted_reset']}\n";
echo "  Capped (1 year max): {$stats['capped']}\n";
echo "  Unchanged: {$stats['unchanged']}\n";

if ($modified) {
    if ($dryRun) {
        echo "\n🔍 DRY RUN - no changes written\n";
    } else {
        file_put_contents($filePath, $content);
        echo "\n✅ File updated!\n";
    }
} else {
    echo "\n✨ No changes needed\n";
}

exit(0);
