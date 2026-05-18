<?php
require_once '../includes/config.php';
requireAdmin();
$pdo = getDB();
$action = $_POST['action'] ?? '';

if($action === 'add'){
  $name = trim($_POST['name']??'');
  $user_id = (int)($_POST['user_id']??0);
  $incubator_id = (int)($_POST['incubator_id']??0);
  $start_date = $_POST['start_date']??'';
  $hatch_date = $_POST['hatch_date']??'';
  if(empty($name)||!$user_id||!$incubator_id||empty($start_date)||empty($hatch_date)){ jsonResponse(['success'=>false,'message'=>'Required fields missing']); }
  $stmt = $pdo->prepare("INSERT INTO batches (incubator_id,user_id,batch_name,egg_type,egg_count,start_date,expected_hatch_date,notes) VALUES (?,?,?,?,?,?,?,?)");
  $stmt->execute([$incubator_id,$user_id,$name,$_POST['egg_type']??'Chicken',(int)($_POST['egg_count']??0),$start_date,$hatch_date,trim($_POST['notes']??'')]);
  jsonResponse(['success'=>true]);
}
if($action === 'update'){
  $id = (int)($_POST['id']??0);
  $status = $_POST['status']??'incubating';
  $stmt = $pdo->prepare(
    "UPDATE batches
        SET batch_name=?,egg_type=?,egg_count=?,expected_hatch_date=?,status=?,
            completed_at = CASE WHEN ?='completed' THEN NOW() ELSE completed_at END,
            terminated_at = CASE WHEN ?='terminated' THEN NOW() ELSE terminated_at END
     WHERE id=?"
  );
  $stmt->execute([
    $_POST['name']??'',
    $_POST['egg_type']??'Chicken',
    (int)($_POST['egg_count']??0),
    $_POST['hatch_date']??'',
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
