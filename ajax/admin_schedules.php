<?php
require_once '../includes/config.php';
requireAdmin();
$pdo = getDB();
$action = $_POST['action'] ?? '';

function parseScheduleDateTime($date, $time) {
  $date = trim((string)$date);
  $time = trim((string)$time);
  if ($date === '' || $time === '') {
    return null;
  }
  $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . substr($time, 0, 5));
  return $dt ?: null;
}

function adminScheduleDurationSeconds(array $payload): int {
  $description = [];
  if (!empty($payload['description'])) {
    $decoded = json_decode((string)$payload['description'], true);
    if (is_array($decoded)) {
      $description = $decoded;
    }
  }

  if (isset($description['session_duration_sec'])) {
    return max(1, (int)$description['session_duration_sec']);
  }

  if (isset($payload['duration_hours']) && $payload['duration_hours'] !== null && $payload['duration_hours'] !== '') {
    return max(1, (int)round(((float)$payload['duration_hours']) * 3600));
  }

  return 21 * 86400;
}

function adminExistingScheduleWindows(PDO $pdo, int $incubatorId, int $excludeScheduleId = 0): array {
  $windows = [];

  $scheduleSql = "SELECT s.id, s.title, s.scheduled_date, s.scheduled_time, s.description, s.status
                  FROM schedules s
                  WHERE s.incubator_id = ?" . ($excludeScheduleId > 0 ? " AND s.id <> ?" : "") . "
                    AND s.status IN ('pending','running')";
  $scheduleStmt = $pdo->prepare($scheduleSql);
  $params = [$incubatorId];
  if ($excludeScheduleId > 0) {
    $params[] = $excludeScheduleId;
  }
  $scheduleStmt->execute($params);
  foreach ($scheduleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $start = parseScheduleDateTime($row['scheduled_date'], $row['scheduled_time']);
    if (!$start) {
      continue;
    }
    $windows[] = [
      'id' => (int)$row['id'],
      'title' => $row['title'],
      'start' => $start->getTimestamp(),
      'end' => $start->getTimestamp() + adminScheduleDurationSeconds($row)
    ];
  }

  $batchStmt = $pdo->prepare(
    "SELECT id, batch_name, start_date, expected_hatch_date, notes, status
     FROM batches
     WHERE incubator_id = ?
       AND status IN ('scheduled','incubating')"
  );
  $batchStmt->execute([$incubatorId]);
  foreach ($batchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $start = null;
    if (!empty($row['notes']) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $row['notes'], $matches)) {
      $start = DateTime::createFromFormat('Y-m-d H:i:s', $matches[1]);
    }
    if (!$start) {
      $start = DateTime::createFromFormat('Y-m-d H:i:s', $row['start_date'] . ' 00:00:00');
    }
    $end = DateTime::createFromFormat('Y-m-d H:i:s', $row['expected_hatch_date'] . ' 23:59:59');
    if (!$start || !$end) {
      continue;
    }
    $windows[] = [
      'id' => (int)$row['id'],
      'title' => $row['batch_name'],
      'start' => $start->getTimestamp(),
      'end' => $end->getTimestamp()
    ];
  }

  return $windows;
}

function adminHasConflict(array $existingWindows, int $newStart, int $newEnd) {
  foreach ($existingWindows as $window) {
    if ($newStart < $window['end'] && $newEnd > $window['start']) {
      return $window;
    }
  }
  return null;
}

if($action === 'add'){
  $title = trim($_POST['title']??'');
  $incubator_id = (int)($_POST['incubator_id']??0);
  $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
  $date = $_POST['date']??'';
  $time = $_POST['time']??'';
  $action_type = $_POST['action_type']??'turning';
  $target_temp = !empty($_POST['target_temp']) ? (float)$_POST['target_temp'] : null;
  $target_humidity = !empty($_POST['target_humidity']) ? (float)$_POST['target_humidity'] : null;
  $notes = trim($_POST['notes']??'');
  if(empty($title)||!$incubator_id||empty($date)||empty($time)){ jsonResponse(['success'=>false,'message'=>'Title, incubator, date and time required']); }

  $startAt = parseScheduleDateTime($date, $time);
  if (!$startAt) {
    jsonResponse(['success' => false, 'message' => 'Invalid start date or time']);
  }
  $durationSeconds = adminScheduleDurationSeconds([
    'description' => $notes,
    'duration_hours' => $_POST['duration_hours'] ?? null
  ]);
  $newStart = $startAt->getTimestamp();
  $newEnd = $newStart + $durationSeconds;

  $conflict = adminHasConflict(adminExistingScheduleWindows($pdo, $incubator_id), $newStart, $newEnd);
  if ($conflict) {
    jsonResponse(['success' => false, 'message' => 'Schedule conflict detected. Another scheduled session overlaps this time range.']);
  }

  $statusToInsert = 'pending';
  if ($startAt) {
    $nowTs = time();
    $newStart = $startAt->getTimestamp();
    if ($newStart <= $nowTs) {
      $hw = $pdo->prepare("SELECT device_status, last_seen FROM hardware_state WHERE incubator_id=? LIMIT 1");
      $hw->execute([$incubator_id]);
      $hwRow = $hw->fetch(PDO::FETCH_ASSOC);
      $isOffline = true;
      if ($hwRow) {
        $ds = $hwRow['device_status'] ?? 'offline';
        $lastSeen = !empty($hwRow['last_seen']) ? strtotime($hwRow['last_seen']) : 0;
        if ($ds === 'online' || ($lastSeen && ($nowTs - $lastSeen) < 10)) {
          $isOffline = false;
        }
      }
      if ($isOffline) {
        $statusToInsert = 'failed';
      }
    }
  }

  $stmt = $pdo->prepare("INSERT INTO schedules (incubator_id,batch_id,title,description,scheduled_date,scheduled_time,action_type,target_temp,target_humidity,status,created_by_role,created_by_id) VALUES (?,?,?,?,?,?,?,?,?,?, 'admin',?)");
  $stmt->execute([$incubator_id,$batch_id,$title,$notes,$date,$time,$action_type,$target_temp,$target_humidity,$statusToInsert,$_SESSION['admin_id']]);
  logActivity('admin',$_SESSION['admin_id'],'Add Schedule',"Added: $title (status={$statusToInsert})");

  if ($statusToInsert === 'failed' && $batch_id) {
    $pdo->prepare("UPDATE batches SET status='failed', terminated_at=NOW() WHERE id=?")->execute([$batch_id]);
  }

  jsonResponse(['success'=>true, 'status'=>$statusToInsert]);
}

if($action === 'update'){
  $id = (int)($_POST['id']??0);
  $title = trim($_POST['title']??'');
  $date = $_POST['date']??'';
  $time = $_POST['time']??'';
  $action_type = $_POST['action_type']??'turning';
  $status = $_POST['status']??'pending';
  $notes = trim($_POST['notes']??'');
  $existingStmt = $pdo->prepare("SELECT incubator_id, batch_id FROM schedules WHERE id=? LIMIT 1");
  $existingStmt->execute([$id]);
  $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
  if (!$existing) { jsonResponse(['success'=>false,'message'=>'Schedule not found']); }

  $incubator_id = (int)($existing['incubator_id'] ?? 0);
  $startAt = parseScheduleDateTime($date, $time);
  if (!$startAt) {
    jsonResponse(['success' => false, 'message' => 'Invalid start date or time']);
  }
  $durationSeconds = adminScheduleDurationSeconds(['description' => $notes]);
  $newStart = $startAt->getTimestamp();
  $newEnd = $newStart + $durationSeconds;
  $conflict = adminHasConflict(adminExistingScheduleWindows($pdo, $incubator_id, $id), $newStart, $newEnd);
  if ($conflict) {
    jsonResponse(['success' => false, 'message' => 'Schedule conflict detected. Another scheduled session overlaps this time range.']);
  }

  $stmt = $pdo->prepare("UPDATE schedules SET title=?,scheduled_date=?,scheduled_time=?,action_type=?,status=?,description=? WHERE id=?");
  $stmt->execute([$title,$date,$time,$action_type,$status,$notes,$id]);
  logActivity('admin',$_SESSION['admin_id'],'Update Schedule',"Updated schedule ID: $id");
  jsonResponse(['success'=>true]);
}

if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $stmt = $pdo->prepare("SELECT batch_id FROM schedules WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $batchId = (int)($stmt->fetchColumn() ?: 0);

  $pdo->prepare("UPDATE schedules SET status='cancelled' WHERE id=?")->execute([$id]);
  if ($batchId) {
    $pdo->prepare("UPDATE batches SET status='cancelled', terminated_at = COALESCE(terminated_at, NOW()) WHERE id=?")
        ->execute([$batchId]);
  }
  jsonResponse(['success'=>true]);
}

if($action === 'mark_done'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("UPDATE schedules SET status='done' WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}
?>
