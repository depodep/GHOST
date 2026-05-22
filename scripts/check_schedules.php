<?php
require_once __DIR__ . '/../includes/config.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Schedule status report:\n\n";

// Last run timestamp file
$lastFile = __DIR__ . '/run_schedules.last';
if (file_exists($lastFile)) {
    $ts = trim(file_get_contents($lastFile));
    echo "Last scheduler run (UTC): $ts\n";
} else {
    echo "Last scheduler run: never\n";
}

$pdo = getDB();

$counts = $pdo->query("SELECT
    SUM(status = 'pending') AS pending_schedules,
    SUM(status = 'running') AS running_schedules
  FROM schedules")->fetch(PDO::FETCH_ASSOC);

echo "Pending schedules: " . ($counts['pending_schedules'] ?? 0) . "\n";
echo "Running schedules: " . ($counts['running_schedules'] ?? 0) . "\n\n";

// Batches by status
$batches = $pdo->query("SELECT status, COUNT(*) AS cnt FROM batches GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "Batches by status:\n";
foreach ($batches as $b) {
    echo "  {$b['status']}: {$b['cnt']}\n";
}

echo "\nNext up: Pending schedules (top 10):\n";
$stmt = $pdo->query("SELECT id, incubator_id, batch_id, scheduled_date, scheduled_time, action_type FROM schedules WHERE status = 'pending' ORDER BY scheduled_date ASC, scheduled_time ASC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "  (none)\n";
} else {
    foreach ($rows as $r) {
        echo sprintf("  id=%d incubator=%s batch=%s scheduled=%s %s action=%s\n",
            $r['id'], $r['incubator_id'], $r['batch_id'] ?? 'NULL', $r['scheduled_date'], $r['scheduled_time'], $r['action_type']);
    }
}

echo "\nDone.\n";
