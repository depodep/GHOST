<?php
require_once '../includes/config.php';
$pdo = getDB();
$action = $_POST['action'] ?? '';

if($action === 'login'){
  $email = trim($_POST['email']??'');
  $password = $_POST['password']??'';
  if(empty($email)||empty($password)){ jsonResponse(['success'=>false,'message'=>'All fields required']); }
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='active'");
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if($user && password_verify($password,$user['password'])){
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    logActivity('user',$user['id'],'Login','User logged in');
    jsonResponse(['success'=>true]);
  } else {
    jsonResponse(['success'=>false,'message'=>'Invalid credentials or account suspended']);
  }
}
?>
