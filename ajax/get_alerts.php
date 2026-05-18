<?php
require_once '../includes/config.php';
$pdo = getDB();

if(isset($_GET['count'])){
  $count = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read=0")->fetchColumn();
  jsonResponse(['count'=>(int)$count]);
}

$action = $_POST['action'] ?? '';
if($action === 'mark_read'){
  $id = (int)($_POST['id']??0);
  $pdo->prepare("UPDATE alerts SET is_read=1 WHERE id=?")->execute([$id]);
  jsonResponse(['success'=>true]);
}
if($action === 'mark_all_read'){
  $pdo->query("UPDATE alerts SET is_read=1");
  jsonResponse(['success'=>true]);
}
?>
