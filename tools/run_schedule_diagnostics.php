<?php
// Diagnostic runner to list pending schedules and invoke ScheduleChecker for a specific incubator or all.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ScheduleChecker.php';

$pdo = getDB();
$incubatorId = $argv[1] ?? null;
if ($incubatorId !== null) $incubatorId = (int)$incubatorId;

echo "Running schedule diagnostics\n";

// List pending schedules
$sql = "SELECT s.id, s.incubator_id, s.batch_id, s.scheduled_date, s.scheduled_time, s.status, b.status AS batch_status
          FROM schedules s
          LEFT JOIN batches b ON b.id = s.batch_id
          WHERE s.status = 'pending'";
if ($incubatorId) {
    $sql .= " AND s.incubator_id = " . (int)$incubatorId;
}
$sql .= " ORDER BY s.scheduled_date ASC, s.scheduled_time ASC";

$stmt = $pdo->query($sql);
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pending)) {
    echo "No pending schedules found.\n";
} else {
    echo "Pending schedules:\n";
    foreach ($pending as $p) {
        printf(" - schedule=%d incubator=%d batch=%s scheduled=%s %s batch_status=%s\n", $p['id'], $p['incubator_id'], $p['batch_id'] ?? 'NULL', $p['scheduled_date'], $p['scheduled_time'], $p['batch_status'] ?? 'NULL');
    }
}

// Run checker
$checker = new ScheduleChecker($pdo, 5);
$changes = $checker->processPendingSchedules($incubatorId);

echo "Checker changes:\n";
print_r($changes);

echo "Done. Check PHP error_log (web server/PHP-FPM) for ScheduleChecker debug lines.\n";
