<?php
require_once 'includes/config.php';
$pdo = getDB();
$stmt = $pdo->prepare('SELECT session_status, session_started_at, session_ends_at, active_session_name, updated_at FROM hardware_state WHERE incubator_id = ? LIMIT 1');
$stmt->execute([1]);
$hw = $stmt->fetch(PDO::FETCH_ASSOC);
$batchStmt = $pdo->prepare('SELECT id, batch_name, status, egg_count, egg_type, created_at, start_date, expected_hatch_date FROM batches WHERE id = ? LIMIT 1');
// attempt to find most recent batch for incubator 1
$recent = $pdo->prepare('SELECT id FROM batches WHERE incubator_id = ? ORDER BY created_at DESC LIMIT 1');
$recent->execute([1]);
$batchId = $recent->fetchColumn();
$batch = null;
if ($batchId) {
    $batchStmt->execute([$batchId]);
    $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
}
echo json_encode(['hardware_state' => $hw, 'latest_batch' => $batch], JSON_PRETTY_PRINT);
