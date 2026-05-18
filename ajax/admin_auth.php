<?php
// ajax/admin_auth.php
require_once '../includes/config.php';
$action = $_POST['action'] ?? '';
$pdo = getDB();

if($action === 'login'){
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  if(empty($email)||empty($password)){ jsonResponse(['success'=>false,'message'=>'All fields required']); }
  $stmt = $pdo->prepare("SELECT * FROM admins WHERE email=? AND status='active'");
  $stmt->execute([$email]);
  $admin = $stmt->fetch();
  if($admin && password_verify($password,$admin['password'])){
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    logActivity('admin',$admin['id'],'Login','Admin logged in');
    jsonResponse(['success'=>true]);
  } else {
    jsonResponse(['success'=>false,'message'=>'Invalid email or password']);
  }
}

if($action === 'logout'){
  session_destroy();
  jsonResponse(['success'=>true]);
}
?>
