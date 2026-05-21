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

function detectIncubatorAssignmentColumn(PDO $pdo): ?string {
    $cols = $pdo->query("SHOW COLUMNS FROM incubators")->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($cols as $c) {
        $map[$c['Field']] = true;
    }
    if (isset($map['user_id'])) {
        return 'user_id';
    }
    if (isset($map['owner_id'])) {
        return 'owner_id';
    }
    return null;
}

function normalizeIncubatorIds($value): array {
    if (is_array($value)) {
        $arr = $value;
    } elseif ($value === null || $value === '') {
        $arr = [];
    } else {
        $arr = explode(',', (string)$value);
    }

    $ids = [];
    foreach ($arr as $v) {
        $id = (int)$v;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function assignUserIncubators(PDO $pdo, int $userId, array $incubatorIds): void {
    $assignmentColumn = detectIncubatorAssignmentColumn($pdo);
    if ($assignmentColumn === null) {
        return;
    }

    $pdo->prepare("UPDATE incubators SET {$assignmentColumn} = NULL WHERE {$assignmentColumn} = ?")
        ->execute([$userId]);

    if (count($incubatorIds) === 0) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($incubatorIds), '?'));
    $params = array_merge([$userId], $incubatorIds);
    $pdo->prepare("UPDATE incubators SET {$assignmentColumn} = ? WHERE id IN ({$placeholders})")
        ->execute($params);
}

function validateIncubatorIdsExist(PDO $pdo, array $incubatorIds): array {
    $incubatorIds = array_values(array_unique(array_filter(array_map('intval', $incubatorIds), function ($id) {
        return $id > 0;
    })));

    if (count($incubatorIds) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($incubatorIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM incubators WHERE id IN ({$placeholders})");
    $stmt->execute($incubatorIds);
    $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    sort($found);
    $requested = $incubatorIds;
    sort($requested);

    if ($found !== $requested) {
        jsonResponse(['success' => false, 'message' => 'One or more selected incubators do not exist.']);
    }

    return $incubatorIds;
}

function getUserStatusById(PDO $pdo, int $userId): ?string {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $status = $stmt->fetchColumn();
    return $status !== false ? (string)$status : null;
}

/* ══════════════════════════════════════════
   ADD USER
══════════════════════════════════════════ */
if ($action === 'add') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $status   =      $_POST['status']   ?? 'active';
    $password =      $_POST['password'] ?? '';
    $incubatorIds = validateIncubatorIdsExist($pdo, normalizeIncubatorIds($_POST['incubator_ids'] ?? []));

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
    $newUserId = (int)$pdo->lastInsertId();

    assignUserIncubators($pdo, $newUserId, $incubatorIds);

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
    $incubatorIds = validateIncubatorIdsExist($pdo, normalizeIncubatorIds($_POST['incubator_ids'] ?? []));

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

    assignUserIncubators($pdo, $id, $incubatorIds);

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

    $activeBatchCountStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM batches
         WHERE user_id = ?
           AND status IN ('scheduled', 'incubating')"
    );
    $activeBatchCountStmt->execute([$id]);
    $activeBatchCount = (int)$activeBatchCountStmt->fetchColumn();
    if ($activeBatchCount > 0) {
        $reassignToUserId = (int)($_POST['reassign_to_user_id'] ?? 0);
        if ($reassignToUserId <= 0) {
        jsonResponse([
            'success' => false,
            'message' => 'Cannot delete this user while they still own active batches. Reassign or finish those batches first.'
        ]);
        }

        if ($reassignToUserId === $id) {
            jsonResponse(['success' => false, 'message' => 'You cannot reassign batches to the same user being deleted.']);
        }

        $targetStatus = getUserStatusById($pdo, $reassignToUserId);
        if ($targetStatus === null) {
            jsonResponse(['success' => false, 'message' => 'Reassignment user not found.']);
        }
        if ($targetStatus !== 'active') {
            jsonResponse(['success' => false, 'message' => 'Reassignment user must be active.']);
        }

        $pdo->prepare("UPDATE batches SET user_id = ? WHERE user_id = ? AND status IN ('scheduled', 'incubating')")
            ->execute([$reassignToUserId, $id]);
    }

    $assignmentColumn = detectIncubatorAssignmentColumn($pdo);
    if ($assignmentColumn !== null) {
        $pdo->prepare("UPDATE incubators SET {$assignmentColumn} = NULL WHERE {$assignmentColumn} = ?")
            ->execute([$id]);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    logActivity('admin', $_SESSION['admin_id'], 'Delete User', "Deleted user ID: {$id}");
    jsonResponse(['success' => true, 'message' => 'User deleted successfully.']);
}

/* ══════════════════════════════════════════
   RESET PASSWORD (DEDICATED ACTION)
══════════════════════════════════════════ */
if ($action === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $forceInactiveConfirm = (string)($_POST['force_inactive_confirm'] ?? '0');

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID.']);
    }
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    }

    $exists = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $exists->execute([$id]);
    if (!$exists->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => 'User not found.']);
    }

    $statusStmt = $pdo->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
    $statusStmt->execute([$id]);
    $userStatus = strtolower((string)$statusStmt->fetchColumn());
    if (in_array($userStatus, ['inactive', 'suspended'], true) && $forceInactiveConfirm !== '1') {
        jsonResponse(['success' => false, 'message' => 'Password reset for inactive/suspended users requires confirmation.']);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$hash, $id]);

    logActivity('admin', $_SESSION['admin_id'], 'Reset User Password', "Reset password for user ID: {$id}");
    jsonResponse(['success' => true, 'message' => 'Password reset successfully.']);
}

/* Unknown or missing action */
jsonResponse(['success' => false, 'message' => 'Unknown action.']);
