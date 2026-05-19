<?php
require_once '../includes/config.php';
requireUser();
$pdo = getDB();
$uid = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if($action === 'add'){
  $name = trim($_POST['name']??'');
  $incubator_id = (int)($_POST['incubator_id']??0);
  $egg_type = $_POST['egg_type']??'Chicken';
  $egg_count = (int)($_POST['egg_count']??0);
  $start_date = $_POST['start_date']??'';
  $hatch_date = $_POST['hatch_date']??'';
  $notes = trim($_POST['notes']??'');
  if(empty($name)||!$incubator_id||empty($start_date)||empty($hatch_date)){ jsonResponse(['success'=>false,'message'=>'Required fields missing']); }
  $stmt = $pdo->prepare("INSERT INTO batches (incubator_id,user_id,batch_name,egg_type,egg_count,start_date,expected_hatch_date,notes) VALUES (?,?,?,?,?,?,?,?)");
  $stmt->execute([$incubator_id,$uid,$name,$egg_type,$egg_count,$start_date,$hatch_date,$notes]);
  logActivity('user',$uid,'Add Batch',"Started: $name");
  jsonResponse(['success'=>true]);
}

if($action === 'update'){
  $id = (int)($_POST['id']??0);
  if (!$id) { jsonResponse(['success'=>false,'message'=>'Missing id']); }
  $existingStmt = $pdo->prepare("SELECT batch_name, egg_type, egg_count, expected_hatch_date, status, notes FROM batches WHERE id=? AND user_id=? LIMIT 1");
  $existingStmt->execute([$id, $uid]);
  $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
  if (!$existing) { jsonResponse(['success'=>false,'message'=>'Batch not found']); }

  $batchName = (isset($_POST['name']) && $_POST['name'] !== '') ? trim($_POST['name']) : $existing['batch_name'];
  $eggType = (isset($_POST['egg_type']) && $_POST['egg_type'] !== '') ? $_POST['egg_type'] : $existing['egg_type'];
  $eggCount = array_key_exists('egg_count', $_POST) ? (int)($_POST['egg_count']) : (int)$existing['egg_count'];
  $hatchDate = (isset($_POST['hatch_date']) && $_POST['hatch_date'] !== '') ? $_POST['hatch_date'] : $existing['expected_hatch_date'];
  $notes = array_key_exists('notes', $_POST) ? trim($_POST['notes']) : $existing['notes'];
  $status = $_POST['status'] ?? $existing['status'];
  $stmt = $pdo->prepare(
    "UPDATE batches
        SET batch_name=?,egg_type=?,egg_count=?,expected_hatch_date=?,status=?,notes=?,
            completed_at = CASE WHEN ?='completed' THEN NOW() ELSE completed_at END,
            terminated_at = CASE WHEN ?='terminated' THEN NOW() ELSE terminated_at END
     WHERE id=? AND user_id=?"
  );
  $stmt->execute([
    $batchName,
    $eggType,
    $eggCount,
    $hatchDate,
    $status,
    $notes,
    $status,
    $status,
    $id,
    $uid
  ]);
  jsonResponse(['success'=>true]);
}

if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("DELETE FROM batches WHERE id=? AND user_id=?")->execute([$id,$uid]);
  jsonResponse(['success'=>true]);
}

if($action === 'get_active_egg_count'){
  $incubator_id = (int)($_POST['incubator_id']??0);
  if(!$incubator_id){ jsonResponse(['success'=>false,'message'=>'Missing incubator_id']); }
  
  // First try to get from active incubating batch
  $stmt = $pdo->prepare("SELECT egg_count FROM batches WHERE incubator_id=? AND status='incubating' ORDER BY created_at DESC LIMIT 1");
  $stmt->execute([$incubator_id]);
  $eggCount = (int)($stmt->fetchColumn() ?: 0);
  
  // If no active batch, get from most recent batch (completed or failed)
  if($eggCount === 0) {
    $stmt = $pdo->prepare("SELECT egg_count FROM batches WHERE incubator_id=? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$incubator_id]);
    $eggCount = (int)($stmt->fetchColumn() ?: 0);
  }
  
  jsonResponse(['success'=>true,'egg_count'=>$eggCount]);
}

if($action === 'summary'){
  $batch_id = (int)($_POST['batch_id'] ?? 0);
  if (!$batch_id) {
    jsonResponse(['success' => false, 'message' => 'Missing batch_id']);
  }

  $stmt = $pdo->prepare(
    "SELECT b.*, i.name AS incubator_name
     FROM batches b
     JOIN incubators i ON b.incubator_id = i.id
     WHERE b.id = ? AND b.user_id = ?
     LIMIT 1"
  );
  $stmt->execute([$batch_id, $uid]);
  $batch = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$batch) {
    jsonResponse(['success' => false, 'message' => 'Batch not found']);
  }

  $windowStart = $batch['start_date'] ? ($batch['start_date'] . ' 00:00:00') : null;
  $windowEnd = null;
  if (!empty($batch['completed_at'])) {
    $windowEnd = $batch['completed_at'];
  } elseif (!empty($batch['terminated_at'])) {
    $windowEnd = $batch['terminated_at'];
  } elseif (!empty($batch['expected_hatch_date'])) {
    $windowEnd = $batch['expected_hatch_date'] . ' 23:59:59';
  } else {
    $windowEnd = date('Y-m-d H:i:s');
  }

  $tempStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS log_count,
        MIN(temperature) AS min_temp,
        MAX(temperature) AS max_temp,
        AVG(temperature) AS avg_temp,
        MIN(humidity) AS min_humidity,
        MAX(humidity) AS max_humidity,
        AVG(humidity) AS avg_humidity,
        MIN(recorded_at) AS first_log,
        MAX(recorded_at) AS last_log
     FROM temperature_logs
     WHERE incubator_id = ?
       AND recorded_at >= ?
       AND recorded_at <= ?"
  );
  $tempStmt->execute([$batch['incubator_id'], $windowStart, $windowEnd]);
  $temps = $tempStmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $logsStmt = $pdo->prepare(
    "SELECT recorded_at, temperature, humidity
     FROM temperature_logs
     WHERE incubator_id = ?
       AND recorded_at >= ?
       AND recorded_at <= ?
     ORDER BY recorded_at ASC"
  );
  $logsStmt->execute([$batch['incubator_id'], $windowStart, $windowEnd]);
  $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

  $turnStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM schedules
     WHERE incubator_id = ?
       AND batch_id = ?
       AND action_type = 'turning'
       AND status = 'done'"
  );
  $turnStmt->execute([$batch['incubator_id'], $batch_id]);
  $swingCount = (int)($turnStmt->fetchColumn() ?: 0);

  $durationSeconds = 0;
  if (!empty($batch['completed_at'])) {
    $durationSeconds = max(0, strtotime($batch['completed_at']) - strtotime($batch['created_at']));
  } elseif (!empty($batch['terminated_at'])) {
    $durationSeconds = max(0, strtotime($batch['terminated_at']) - strtotime($batch['created_at']));
  } else {
    $durationSeconds = max(0, time() - strtotime($batch['created_at']));
  }

  jsonResponse([
    'success' => true,
    'batch' => [
      'id' => (int)$batch['id'],
      'batch_name' => $batch['batch_name'],
      'egg_type' => $batch['egg_type'],
      'egg_count' => (int)$batch['egg_count'],
      'status' => $batch['status'],
      'incubator_name' => $batch['incubator_name'],
      'start_date' => $batch['start_date'],
      'expected_hatch_date' => $batch['expected_hatch_date'],
      'completed_at' => $batch['completed_at'] ?? null,
      'terminated_at' => $batch['terminated_at'] ?? null,
      'created_at' => $batch['created_at']
    ],
    'session' => [
      'start_at' => $windowStart,
      'end_at' => $windowEnd,
      'duration_seconds' => $durationSeconds,
      'swing_count' => $swingCount,
      'temperature' => [
        'count' => (int)($temps['log_count'] ?? 0),
        'min' => $temps['min_temp'] !== null ? (float)$temps['min_temp'] : null,
        'max' => $temps['max_temp'] !== null ? (float)$temps['max_temp'] : null,
        'avg' => $temps['avg_temp'] !== null ? round((float)$temps['avg_temp'], 2) : null,
      ],
      'humidity' => [
        'min' => $temps['min_humidity'] !== null ? (float)$temps['min_humidity'] : null,
        'max' => $temps['max_humidity'] !== null ? (float)$temps['max_humidity'] : null,
        'avg' => $temps['avg_humidity'] !== null ? round((float)$temps['avg_humidity'], 2) : null,
      ],
      'logs' => $logs,
      'first_log' => $temps['first_log'] ?? null,
      'last_log' => $temps['last_log'] ?? null
    ]
  ]);
}
?>
