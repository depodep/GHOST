<?php
require_once '../includes/config.php';
requireAdmin();
$pdo = getDB();
$action = $_POST['action'] ?? '';

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

  $startAt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . substr($time,0,5));
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
        if ($ds === 'online' || ($lastSeen && ($nowTs - $lastSeen) < 300)) {
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
  $stmt = $pdo->prepare("UPDATE schedules SET title=?,scheduled_date=?,scheduled_time=?,action_type=?,status=?,description=? WHERE id=?");
  $stmt->execute([$title,$date,$time,$action_type,$status,$notes,$id]);
  logActivity('admin',$_SESSION['admin_id'],'Update Schedule',"Updated schedule ID: $id");
  jsonResponse(['success'=>true]);
}

if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("DELETE FROM schedules WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}

if($action === 'mark_done'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("UPDATE schedules SET status='done' WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}
?>
