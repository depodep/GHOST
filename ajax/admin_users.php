<?php
require_once '../includes/config.php';

/* Always respond with JSON — never redirect for AJAX requests */
header('Content-Type: application/json; charset=utf-8');

/* Auth check */
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and log in again.']);
    exit();
}

$pdo    = getDB();
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

/* ══════════════════════════════════════════
   ADD USER
══════════════════════════════════════════ */
if ($action === 'add') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $status   =      $_POST['status']   ?? 'active';
    $password =      $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Name, email and password are required.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
    }
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $status = 'active';
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'That email address is already in use.']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, $phone ?: null, $status]);

    logActivity('admin', $_SESSION['admin_id'], 'Add User', "Added user: {$name}");
    jsonResponse(['success' => true, 'message' => 'User added successfully.']);
}

/* ══════════════════════════════════════════
   UPDATE USER
══════════════════════════════════════════ */
if ($action === 'update') {
    $id       = (int) ($_POST['id']       ?? 0);
    $name     = trim($_POST['name']       ?? '');
    $email    = trim($_POST['email']      ?? '');
    $phone    = trim($_POST['phone']      ?? '');
    $status   =      $_POST['status']     ?? 'active';
    $password =      $_POST['password']   ?? '';

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID.']);
    }
    if (empty($name) || empty($email)) {
        jsonResponse(['success' => false, 'message' => 'Name and email are required.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
    }
    if ($password !== '' && strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    }
    if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $status = 'active';
    }

    /* Check email uniqueness, excluding this user */
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'That email address is already used by another user.']);
    }

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, status=?, password=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $email, $phone ?: null, $status, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$name, $email, $phone ?: null, $status, $id]);
    }

    logActivity('admin', $_SESSION['admin_id'], 'Update User', "Updated user ID: {$id}");
    jsonResponse(['success' => true, 'message' => 'User updated successfully.']);
}

/* ══════════════════════════════════════════
   DELETE USER
══════════════════════════════════════════ */
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID.']);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    logActivity('admin', $_SESSION['admin_id'], 'Delete User', "Deleted user ID: {$id}");
    jsonResponse(['success' => true, 'message' => 'User deleted successfully.']);
}

/* Unknown or missing action */
jsonResponse(['success' => false, 'message' => 'Unknown action.']);
