<?php
require_once '../includes/config.php';
requireAdmin();
$pdo = getDB();
$action = $_POST['action'] ?? '';

if($action === 'add'){
  jsonResponse(['success' => false, 'message' => 'Admin cannot add batches. Use the user scheduler flow.']);
}
if($action === 'update'){
  $id = (int)($_POST['id']??0);
  if (!$id) { jsonResponse(['success'=>false,'message'=>'Missing id']); }
  $existingStmt = $pdo->prepare("SELECT batch_name, egg_type, egg_count, expected_hatch_date, status FROM batches WHERE id=? LIMIT 1");
  $existingStmt->execute([$id]);
  $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
  if (!$existing) { jsonResponse(['success'=>false,'message'=>'Batch not found']); }

  $batchName = (isset($_POST['name']) && $_POST['name'] !== '') ? trim($_POST['name']) : $existing['batch_name'];
  $eggType = (isset($_POST['egg_type']) && $_POST['egg_type'] !== '') ? $_POST['egg_type'] : $existing['egg_type'];
  $eggCount = array_key_exists('egg_count', $_POST) ? (int)($_POST['egg_count']) : (int)$existing['egg_count'];
  $hatchDate = (isset($_POST['hatch_date']) && $_POST['hatch_date'] !== '') ? $_POST['hatch_date'] : $existing['expected_hatch_date'];
  $status = $_POST['status'] ?? $existing['status'];
  $stmt = $pdo->prepare(
    "UPDATE batches
        SET batch_name=?,egg_type=?,egg_count=?,expected_hatch_date=?,status=?,
            completed_at = CASE WHEN ?='completed' THEN NOW() ELSE completed_at END,
            terminated_at = CASE WHEN ?='terminated' THEN NOW() ELSE terminated_at END
     WHERE id=?"
  );
  $stmt->execute([
    $batchName,
    $eggType,
    $eggCount,
    $hatchDate,
    $status,
    $status,
    $status,
    $id
  ]);
  jsonResponse(['success'=>true]);
}
if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("DELETE FROM batches WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}
?>
