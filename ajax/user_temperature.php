<?php
require_once '../includes/config.php';
requireUser();
$pdo = getDB();
$action = $_POST['action'] ?? '';

if($action === 'log_temp'){
  $incubator_id = (int)($_POST['incubator_id'] ?? 0);
  $temperature  = (float)($_POST['temperature'] ?? 0);
  $humidity     = !empty($_POST['humidity']) ? (float)$_POST['humidity'] : null;
  $uid          = $_SESSION['user_id'];

  // Make sure this user has a batch in that incubator
  $check = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE incubator_id=? AND user_id=?");
  $check->execute([$incubator_id, $uid]);
  if(!$check->fetchColumn()){
    jsonResponse(['success'=>false,'message'=>'Access denied to this incubator.']);
  }

  $stmt = $pdo->prepare("INSERT INTO temperature_logs (incubator_id,temperature,humidity,recorded_by,recorded_id) VALUES (?,?,?,'user',?)");
  $stmt->execute([$incubator_id, $temperature, $humidity, $uid]);
  logActivity('user', $uid, 'Log Temperature', "Logged temp {$temperature}°C for incubator ID: {$incubator_id}");
  jsonResponse(['success'=>true]);
}

jsonResponse(['success'=>false,'message'=>'Invalid action.']);
?>
