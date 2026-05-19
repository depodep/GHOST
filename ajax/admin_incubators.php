<?php
require_once '../includes/config.php';
requireAdmin();
$pdo    = getDB();
$action = $_POST['action'] ?? '';

if($action === 'add'){
  $name = trim($_POST['name']??'');
  if(empty($name)){ jsonResponse(['success'=>false,'message'=>'Name required']); }
  $stmt = $pdo->prepare("INSERT INTO incubators (name,model,capacity,location,status,owner_id) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$name,$_POST['model']??'',(int)($_POST['capacity']??0),$_POST['location']??'',$_POST['status']??'idle',$_SESSION['admin_id']]);
  $iid = $pdo->lastInsertId();

  // Upsert temperature_settings with provided values
  $tt  = (float)($_POST['target_temp']      ?? 37.50);
  $th  = (float)($_POST['target_humidity']  ?? 55.00);
  $ti  = (float)($_POST['turning_interval'] ?? 8.0);
   if ($ti < 0.01 || $ti > 24) {
     jsonResponse(['success'=>false,'message'=>'Invalid turning interval.']);
   }
  $pdo->prepare(
    "INSERT INTO temperature_settings (incubator_id,target_temp,min_temp,max_temp,target_humidity,min_humidity,max_humidity,turning_interval)
     VALUES (?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE target_temp=VALUES(target_temp),target_humidity=VALUES(target_humidity),turning_interval=VALUES(turning_interval)"
  )->execute([$iid,$tt,round($tt-0.5,2),round($tt+0.5,2),$th,round($th-5,2),round($th+5,2),$ti]);

  logActivity('admin',$_SESSION['admin_id'],'Add Incubator',"Added: $name");
  jsonResponse(['success'=>true]);
}

if($action === 'update'){
  $id = (int)($_POST['id']??0);
  $stmt = $pdo->prepare("UPDATE incubators SET name=?,model=?,capacity=?,location=?,status=? WHERE id=?");
  $stmt->execute([$_POST['name']??'',$_POST['model']??'',(int)($_POST['capacity']??0),$_POST['location']??'',$_POST['status']??'idle',$id]);

  // Update temperature settings if provided
  if(isset($_POST['target_temp']) && $_POST['target_temp'] !== ''){
    $tt  = (float)$_POST['target_temp'];
    $th  = (float)($_POST['target_humidity']  ?? 55.00);
    $ti  = (float)($_POST['turning_interval'] ?? 8.0);
     if ($ti < 0.01 || $ti > 24) {
       jsonResponse(['success'=>false,'message'=>'Invalid turning interval.']);
     }
    $pdo->prepare(
      "INSERT INTO temperature_settings (incubator_id,target_temp,min_temp,max_temp,target_humidity,min_humidity,max_humidity,turning_interval)
       VALUES (?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE target_temp=VALUES(target_temp),min_temp=VALUES(min_temp),max_temp=VALUES(max_temp),
         target_humidity=VALUES(target_humidity),min_humidity=VALUES(min_humidity),max_humidity=VALUES(max_humidity),
         turning_interval=VALUES(turning_interval)"
    )->execute([$id,$tt,round($tt-0.5,2),round($tt+0.5,2),$th,round($th-5,2),round($th+5,2),$ti]);
  }

  logActivity('admin',$_SESSION['admin_id'],'Edit Incubator',"Updated ID: $id");
  jsonResponse(['success'=>true]);
}

if($action === 'delete'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("DELETE FROM incubators WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}
