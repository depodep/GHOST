<?php
// CLI runner for pending schedules
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ScheduleChecker.php';

$incubator = null;
$graceSeconds = 180; // default 3 minutes grace for offline handling

// Simple CLI arg parsing: support --incubator=ID and --grace=SECONDS or positional incubator
for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--incubator=') === 0) {
        $incubator = (int)substr($arg, strlen('--incubator='));
    } elseif (strpos($arg, '--grace=') === 0) {
        $graceSeconds = (int)substr($arg, strlen('--grace='));
    } elseif (is_numeric($arg) && $incubator === null) {
        $incubator = (int)$arg;
    }
}
$pdo = getDB();
$checker = new ScheduleChecker($pdo, $graceSeconds);

try {
    $res = $checker->processPendingSchedules($incubator);
    echo json_encode($res, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(2);
}
