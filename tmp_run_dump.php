<?php
require_once 'includes/config.php';
$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM hardware_state WHERE incubator_id = ? LIMIT 1');
$stmt->execute([1]);
$hw = $stmt->fetch(PDO::FETCH_ASSOC);
$batchStmt = $pdo->prepare('SELECT id, batch_name, status, egg_count, egg_type, created_at, start_date, expected_hatch_date, notes FROM batches WHERE incubator_id = ? ORDER BY created_at DESC LIMIT 5');
$batchStmt->execute([1]);
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('tmp_dump_output.json', json_encode(['hardware_state' => $hw, 'recent_batches' => $batches], JSON_PRETTY_PRINT));
echo "Wrote tmp_dump_output.json\n";