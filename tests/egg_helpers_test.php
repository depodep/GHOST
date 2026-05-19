<?php
// CLI test for egg helper parsing functions

require_once __DIR__ . '/../user/dashboard.php'; // not ideal but includes common helpers in this workspace

function runTest($name, $callable) {
    echo $name . ': ';
    try {
        $res = $callable();
        echo "OK -> ";
        if (is_int($res) || is_string($res)) echo "$res";
        else var_export($res);
        echo "\n";
    } catch (Exception $e) {
        echo "ERROR - " . $e->getMessage() . "\n";
    }
}

// Test cases for extractBatchStartTimestamp
$note1 = ['notes' => 'SCHEDULED_START: 2026-05-20 15:30:00'];
runTest('extract scheduled start', function() use ($note1) { return extractBatchStartTimestamp($note1); });

$note2 = ['start_date' => '2026-05-21'];
runTest('extract start_date fallback', function() use ($note2) { return extractBatchStartTimestamp($note2); });

// Test cases for displayEggCountForRow
$b1 = ['egg_count' => 12];
runTest('display egg_count numeric', function() use ($b1) { return displayEggCountForRow($b1); });

$b2 = ['notes' => 'EGG_COUNT: 8'];
runTest('display egg_count from notes', function() use ($b2) { return displayEggCountForRow($b2); });

$b3 = ['batch_name' => '50 eggs - Farm'];
runTest('display egg_count from name', function() use ($b3) { return displayEggCountForRow($b3); });

$b4 = [];
runTest('display egg_count missing', function() use ($b4) { return displayEggCountForRow($b4); });

echo "\nTests complete.\n";
