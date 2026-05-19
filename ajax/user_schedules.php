<?php
require_once '../includes/config.php';
requireUser();
$pdo = getDB();
$uid = $_SESSION['user_id'];
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

function durationSecondsFromRequest(array $source) {
  $days = isset($source['duration_days']) ? (int)$source['duration_days'] : 0;
  $hours = isset($source['duration_hours']) ? (int)$source['duration_hours'] : 0;
  $minutes = isset($source['duration_minutes']) ? (int)$source['duration_minutes'] : 0;
  $seconds = isset($source['duration_seconds']) ? (int)$source['duration_seconds'] : 0;
  $duration = ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
  return $duration > 0 ? $duration : 1;
}

function existingScheduleWindows(PDO $pdo, $incubator_id, $excludeScheduleId = 0) {
  $rows = [];

  $stmt = $pdo->prepare(
    "SELECT s.id, s.title, s.scheduled_date, s.scheduled_time, s.status, s.action_type, s.description
     FROM schedules s
     WHERE s.incubator_id = ?
       AND s.status IN ('pending','running')"
       . ($excludeScheduleId > 0 ? " AND s.id <> ?" : "")
  );
  $params = [$incubator_id];
  if ($excludeScheduleId > 0) {
    $params[] = $excludeScheduleId;
  }
  $stmt->execute($params);

  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $start = parseScheduleDateTime($row['scheduled_date'], $row['scheduled_time']);
    if (!$start) {
      continue;
    }
    $duration = 60;
    $description = json_decode((string)($row['description'] ?? ''), true);
    if (is_array($description) && isset($description['session_duration_sec'])) {
      $duration = max(1, (int)$description['session_duration_sec']);
    }
    $rows[] = [
      'id' => (int)$row['id'],
      'title' => $row['title'],
      'start' => $start->getTimestamp(),
      'end' => $start->getTimestamp() + $duration,
      'source' => 'schedule'
    ];
  }

  $batchStmt = $pdo->prepare(
    "SELECT id, batch_name, start_date, expected_hatch_date
     FROM batches
     WHERE incubator_id = ?
       AND status = 'incubating'"
  );
  $batchStmt->execute([$incubator_id]);
  foreach ($batchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (empty($row['start_date']) || empty($row['expected_hatch_date'])) {
      continue;
    }
    $start = DateTime::createFromFormat('Y-m-d H:i:s', $row['start_date'] . ' 00:00:00');
    $end = DateTime::createFromFormat('Y-m-d H:i:s', $row['expected_hatch_date'] . ' 23:59:59');
    if (!$start || !$end) {
      continue;
    }
    $rows[] = [
      'id' => (int)$row['id'],
      'title' => $row['batch_name'],
      'start' => $start->getTimestamp(),
      'end' => $end->getTimestamp(),
      'source' => 'batch'
    ];
  }

  $hwStmt = $pdo->prepare(
    "SELECT session_started_at, session_ends_at, active_session_name, session_status
     FROM hardware_state
     WHERE incubator_id = ?
     LIMIT 1"
  );
  $hwStmt->execute([$incubator_id]);
  $hw = $hwStmt->fetch(PDO::FETCH_ASSOC);
  if ($hw && !empty($hw['session_started_at']) && !empty($hw['session_ends_at']) && $hw['session_status'] === 'running') {
    $start = strtotime($hw['session_started_at']);
    $end = strtotime($hw['session_ends_at']);
    if ($start && $end && $end > $start) {
      $rows[] = [
        'id' => 0,
        'title' => $hw['active_session_name'] ?: 'Active Session',
        'start' => $start,
        'end' => $end,
        'source' => 'active_session'
      ];
    }
  }

  return $rows;
}

function hasConflict(array $existingWindows, $newStart, $newEnd) {
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
  $date = $_POST['start_date'] ?? ($_POST['date']??'');
  $time = $_POST['start_time'] ?? ($_POST['time']??'');
  $action_type = $_POST['action_type']??'turning';
  $target_temp = ($_POST['target_temp'] ?? '') !== '' ? (float)$_POST['target_temp'] : null;
  $min_temp = ($_POST['min_temp'] ?? '') !== '' ? (float)$_POST['min_temp'] : null;
  $max_temp = ($_POST['max_temp'] ?? '') !== '' ? (float)$_POST['max_temp'] : null;
  $target_humidity = ($_POST['target_humidity'] ?? '') !== '' ? (float)$_POST['target_humidity'] : null;
  $min_humidity = ($_POST['min_humidity'] ?? '') !== '' ? (float)$_POST['min_humidity'] : null;
  $max_humidity = ($_POST['max_humidity'] ?? '') !== '' ? (float)$_POST['max_humidity'] : null;
  $swing_duration_sec = ($_POST['swing_duration_sec'] ?? '') !== '' ? (int)$_POST['swing_duration_sec'] : 30;
  $turning_interval = ($_POST['turning_interval'] ?? '') !== '' ? (float)$_POST['turning_interval'] : null;
  $duration_days = ($_POST['duration_days'] ?? '') !== '' ? (int)$_POST['duration_days'] : 21;
  $duration_hours = ($_POST['duration_hours'] ?? '') !== '' ? (int)$_POST['duration_hours'] : 0;
  $duration_minutes = ($_POST['duration_minutes'] ?? '') !== '' ? (int)$_POST['duration_minutes'] : 0;
  $duration_seconds = ($_POST['duration_seconds'] ?? '') !== '' ? (int)$_POST['duration_seconds'] : 0;
  $session_duration_sec = ($duration_days * 86400) + ($duration_hours * 3600) + ($duration_minutes * 60) + $duration_seconds;
  if(empty($title)||!$incubator_id||empty($date)||empty($time)){ jsonResponse(['success'=>false,'message'=>'Required fields missing']); }

  $startAt = parseScheduleDateTime($date, $time);
  if (!$startAt) {
    jsonResponse(['success' => false, 'message' => 'Invalid start date or time']);
  }
  $newStart = $startAt->getTimestamp();
  $newEnd = $newStart + max(1, $session_duration_sec);

  $conflict = hasConflict(existingScheduleWindows($pdo, $incubator_id), $newStart, $newEnd);
  if ($conflict) {
    jsonResponse([
      'success' => false,
      'message' => 'Schedule conflict detected. Another incubation batch is already active within this time range.'
    ]);
  }

  // If the schedule is due now or in the past, verify device connectivity. If the incubator is offline,
  // mark the schedule as 'failed' immediately and mark the associated batch as failed.
  $description = json_encode([
    'start_date' => $date,
    'start_time' => $time,
    'duration_days' => $duration_days,
    'duration_hours' => $duration_hours,
    'duration_minutes' => $duration_minutes,
    'duration_seconds' => $duration_seconds,
    'session_duration_sec' => $session_duration_sec,
    'target_temp' => $target_temp,
    'min_temp' => $min_temp,
    'max_temp' => $max_temp,
    'target_humidity' => $target_humidity,
    'min_humidity' => $min_humidity,
    'max_humidity' => $max_humidity,
    'swing_duration_sec' => $swing_duration_sec,
    'turning_interval' => $turning_interval
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Default to pending
    $statusToInsert = 'pending';
    $nowTs = time();
    if ($newStart <= $nowTs) {
      // Check hardware_state for incubator connectivity
      $hw = $pdo->prepare("SELECT device_status, last_seen FROM hardware_state WHERE incubator_id=? LIMIT 1");
      $hw->execute([$incubator_id]);
      $hwRow = $hw->fetch(PDO::FETCH_ASSOC);
      $isOffline = true;
      if ($hwRow) {
        $ds = $hwRow['device_status'] ?? 'offline';
        $lastSeen = !empty($hwRow['last_seen']) ? strtotime($hwRow['last_seen']) : 0;
        if ($ds === 'online' || ($lastSeen && ($nowTs - $lastSeen) < 300)) {
          $isOffline = false;
        }
      }
      if ($isOffline) {
        $statusToInsert = 'failed';
      }
    }

    $stmt = $pdo->prepare("INSERT INTO schedules (incubator_id,batch_id,title,description,scheduled_date,scheduled_time,action_type,status,created_by_role,created_by_id) VALUES (?,?,?,?,?,?,?,?, 'user', ?)");
    $stmt->execute([$incubator_id,$batch_id,$title,$description,$date,$time,$action_type,$statusToInsert,$uid]);

    // If we marked it failed and there is a batch, mark the batch failed too
    if (isset($statusToInsert) && $statusToInsert === 'failed' && $batch_id) {
      $pdo->prepare("UPDATE batches SET status='failed', terminated_at=NOW() WHERE id=?")->execute([$batch_id]);
    }

    jsonResponse(['success'=>true, 'status'=>$statusToInsert, 'message'=>($statusToInsert==='failed' ? 'Schedule failed to start — incubator appears offline. Batch marked failed.' : 'Schedule saved.')]);
}

if($action === 'update'){
  $id = (int)($_POST['id']??0);
  $title = trim($_POST['title']??'');
  $date = $_POST['date']??'';
  $time = $_POST['time']??'';
  $action_type = $_POST['action_type']??'turning';
  $duration_days = ($_POST['duration_days'] ?? '') !== '' ? (int)$_POST['duration_days'] : 21;
  $duration_hours = ($_POST['duration_hours'] ?? '') !== '' ? (int)$_POST['duration_hours'] : 0;
  $duration_minutes = ($_POST['duration_minutes'] ?? '') !== '' ? (int)$_POST['duration_minutes'] : 0;
  $duration_seconds = ($_POST['duration_seconds'] ?? '') !== '' ? (int)$_POST['duration_seconds'] : 0;
  $session_duration_sec = ($duration_days * 86400) + ($duration_hours * 3600) + ($duration_minutes * 60) + $duration_seconds;

  $stmt0 = $pdo->prepare("SELECT incubator_id FROM schedules WHERE id=? AND created_by_role='user' AND created_by_id=? LIMIT 1");
  $stmt0->execute([$id, $uid]);
  $incubator_id = (int)($stmt0->fetchColumn() ?: 0);
  if (!$incubator_id) {
    jsonResponse(['success' => false, 'message' => 'Schedule not found']);
  }

  $startAt = parseScheduleDateTime($date, $time);
  if (!$startAt) {
    jsonResponse(['success' => false, 'message' => 'Invalid start date or time']);
  }
  $newStart = $startAt->getTimestamp();
  $newEnd = $newStart + max(1, $session_duration_sec);
  $conflict = hasConflict(existingScheduleWindows($pdo, $incubator_id, $id), $newStart, $newEnd);
  if ($conflict) {
    jsonResponse([
      'success' => false,
      'message' => 'Schedule conflict detected. Another incubation batch is already active within this time range.'
    ]);
  }

  $description = json_encode([
    'start_date' => $date,
    'start_time' => $time,
    'duration_days' => $duration_days,
    'duration_hours' => $duration_hours,
    'duration_minutes' => $duration_minutes,
    'duration_seconds' => $duration_seconds,
    'session_duration_sec' => $session_duration_sec
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  $stmt = $pdo->prepare("UPDATE schedules SET title=?,scheduled_date=?,scheduled_time=?,action_type=?,description=? WHERE id=? AND created_by_role='user' AND created_by_id=?");
  $stmt->execute([$title,$date,$time,$action_type,$description,$id,$uid]);
  jsonResponse(['success'=>true]);
}

if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("DELETE FROM schedules WHERE id=? AND created_by_role='user' AND created_by_id=?")->execute([$id,$uid]);
  jsonResponse(['success'=>true]);
}

if($action === 'mark_done'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("UPDATE schedules SET status='done' WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}

// Check for conflicts with existing schedules/batches
if ($action === 'check_conflicts') {
  $incubator_id = (int)($_POST['incubator_id'] ?? 0);
  $start_ts = isset($_POST['start_ts']) ? (int)$_POST['start_ts'] : 0;
  $end_ts = isset($_POST['end_ts']) ? (int)$_POST['end_ts'] : 0;
  if (!$incubator_id || !$start_ts || !$end_ts || $end_ts <= $start_ts) {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters']);
  }

  $existing = existingScheduleWindows($pdo, $incubator_id);
  $conflicts = [];
  foreach ($existing as $w) {
    if ($start_ts < $w['end'] && $end_ts > $w['start']) {
      $conflicts[] = $w;
    }
  }
  jsonResponse(['success' => true, 'conflicts' => $conflicts]);
}

// Cancel schedules (mark as cancelled). Expects schedule_ids[] array.
if ($action === 'cancel_schedules') {
  $ids = $_POST['schedule_ids'] ?? [];
  if (!is_array($ids) || empty($ids)) jsonResponse(['success' => false, 'message' => 'No schedule ids']);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("UPDATE schedules SET status='cancelled' WHERE id IN ($placeholders)");
  $stmt->execute($ids);

  // For any schedules that reference a batch, set that batch to cancelled
  $q = $pdo->prepare("SELECT DISTINCT batch_id FROM schedules WHERE id IN ($placeholders) AND batch_id IS NOT NULL");
  $q->execute($ids);
  $batchIds = array_filter(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN, 0)));
  if (!empty($batchIds)) {
    $ph2 = implode(',', array_fill(0, count($batchIds), '?'));
    $pdo->prepare("UPDATE batches SET status='cancelled', terminated_at=NOW() WHERE id IN ($ph2)")->execute($batchIds);
  }

  jsonResponse(['success' => true]);
}
?>
