<?php
/**
 * Long-running PHP monitor to check schedules/devices periodically.
 * Run from CLI: php scripts/monitor_devices.php [--interval=30] [--grace=120] [--incubator=ID] [--once]
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ScheduleChecker.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the CLI.\n";
    exit(1);
}

$opts = array_slice($argv, 1);
$interval = 30;
$grace = defined('SCHEDULE_OFFLINE_GRACE_SECONDS') ? SCHEDULE_OFFLINE_GRACE_SECONDS : 120;
$incubator = null;
$once = false;

foreach ($opts as $o) {
    if (strpos($o, '--interval=') === 0) {
        $interval = (int)substr($o, strlen('--interval='));
    } elseif (strpos($o, '--grace=') === 0) {
        $grace = (int)substr($o, strlen('--grace='));
    } elseif (strpos($o, '--incubator=') === 0) {
        $incubator = (int)substr($o, strlen('--incubator='));
    } elseif ($o === '--once') {
        $once = true;
    }
}

$interval = max(1, $interval);
$grace = max(1, $grace);

$pdo = getDB();
$checker = new ScheduleChecker($pdo, $grace);

echo sprintf("[monitor_devices] starting: interval=%ds grace=%ds incubator=%s\n", $interval, $grace, $incubator === null ? 'ALL' : $incubator);

// Ensure log/last files exist
$lastFile = __DIR__ . '/monitor_devices.last';

do {
    $start = microtime(true);
    file_put_contents($lastFile, gmdate('c') . "\n");

    try {
        $changes = $checker->processPendingSchedules($incubator);
        $msg = sprintf("[monitor_devices] %s - changes: failed_batches=%d promoted_batches=%d failed_schedules=%d promoted_schedules=%d started_sessions=%d waiting_schedules=%d\n",
            date('c'),
            $changes['failed_batches'] ?? 0,
            $changes['promoted_batches'] ?? 0,
            $changes['failed_schedules'] ?? 0,
            $changes['promoted_schedules'] ?? 0,
            $changes['started_sessions'] ?? 0,
            $changes['waiting_schedules'] ?? 0
        );
        echo $msg;
        error_log($msg);
    } catch (Throwable $e) {
        $err = sprintf("[monitor_devices] %s - exception: %s\n", date('c'), $e->getMessage());
        echo $err;
        error_log($err . "\n" . $e->getTraceAsString());
    }

    $elapsed = microtime(true) - $start;
    $sleep = max(0, $interval - (int)$elapsed);
    if ($once) break;
    sleep($sleep);
} while (true);

echo "[monitor_devices] exiting\n";
