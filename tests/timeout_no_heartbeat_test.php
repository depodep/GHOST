<?php
require_once __DIR__ . '/../includes/config.php';

$pdo = getDB();

// Use a test incubator
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO incubators (name, user_id, incubator_name, location, status) VALUES ('TestInc',1,'Test Incubator','Lab','active')");
    $incubatorId = $pdo->lastInsertId();

    // Create a batch and a running session started 10 minutes ago
    $startAt = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $pdo->exec("INSERT INTO batches (incubator_id, user_id, batch_name, egg_type, egg_count, start_date, expected_hatch_date, status, notes, created_at) VALUES ($incubatorId,1,'Test Batch', 'Chicken', 5, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 21 DAY), 'incubating', NULL, NOW())");
    $batchId = $pdo->lastInsertId();

    $ins = $pdo->prepare("INSERT INTO sessions (incubator_id, batch_id, status, target_temp, duration_hours, started_at) VALUES (?,?,?,?,?,?)");
    $ins->execute([$incubatorId, $batchId, 'running', 37.5, 21, $startAt]);
    $sessionId = $pdo->lastInsertId();

    // hardware_state with no last_seen (simulate device never checked in)
    $pdo->prepare("INSERT INTO hardware_state (incubator_id, device_status, session_status, session_started_at, session_ends_at, active_session_name, last_server_sync) VALUES (?,?,?,?,?,?,NOW())")
        ->execute([$incubatorId, 'offline', 'running', $startAt, null, 'Test Batch']);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Setup error: " . $e->getMessage() . "\n";
    exit(1);
}

// Run scheduler (this should detect stale running session and mark it failed)
$result = runBatchScheduler();

echo "runBatchScheduler result: "; print_r($result);

// Check session status
$stmt = $pdo->prepare("SELECT status, reason_ended FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if ($s && $s['status'] === 'interrupted') {
    echo "Session marked interrupted as expected. reason: " . ($s['reason_ended'] ?? '') . "\n";
} else {
    echo "Session not marked interrupted.\n";
}

// Check batch status
$stmt = $pdo->prepare("SELECT status FROM batches WHERE id = ?");
$stmt->execute([$batchId]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if ($b && $b['status'] === 'failed') {
    echo "Batch marked failed as expected.\n";
} else {
    echo "Batch not marked failed.\n";
}

// Cleanup (optional)
// Note: For now we leave test data so developer can inspect if needed

