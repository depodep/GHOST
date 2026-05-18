<?php
/**
 * user_hw_settings.php
 * Allows authenticated users to update temperature/humidity/swing settings.
 */
require_once '../includes/config.php';
requireUser();
$pdo    = getDB();
$uid    = $_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');

if ($action === 'update_settings') {
    $incubator_id     = (int)($_POST['incubator_id']     ?? 0);
    $target_temp      = (float)($_POST['target_temp']     ?? 37.5);
    $min_temp         = (float)($_POST['min_temp']        ?? 37.0);
    $max_temp         = (float)($_POST['max_temp']        ?? 38.0);
    $target_humidity  = (float)($_POST['target_humidity'] ?? 55.0);
    $min_humidity     = (float)($_POST['min_humidity']    ?? 50.0);
    $max_humidity     = (float)($_POST['max_humidity']    ?? 60.0);
    $turning_interval = (int)($_POST['turning_interval']  ?? 8);

    if (!$incubator_id) {
        jsonResponse(['success' => false, 'message' => 'Missing incubator ID']);
    }
    if ($turning_interval < 1) {
        jsonResponse(['success' => false, 'message' => 'Turning interval too short.']);
    }
    if ($turning_interval > 24) {
        jsonResponse(['success' => false, 'message' => 'Turning interval must be 24 hours or less.']);
    }

    $pdo->prepare(
        "INSERT INTO temperature_settings
            (incubator_id,target_temp,min_temp,max_temp,
             target_humidity,min_humidity,max_humidity,
             turning_interval,updated_by_role,updated_by_id)
         VALUES (?,?,?,?,?,?,?,?,'user',?)
         ON DUPLICATE KEY UPDATE
            target_temp=VALUES(target_temp), min_temp=VALUES(min_temp),
            max_temp=VALUES(max_temp), target_humidity=VALUES(target_humidity),
            min_humidity=VALUES(min_humidity), max_humidity=VALUES(max_humidity),
            turning_interval=VALUES(turning_interval),
            updated_by_role='user', updated_by_id=VALUES(updated_by_id)"
    )->execute([
        $incubator_id, $target_temp, $min_temp, $max_temp,
        $target_humidity, $min_humidity, $max_humidity,
        $turning_interval, $uid
    ]);

    logActivity('user', $uid, 'Update Settings',
        "Updated temp settings for incubator ID: $incubator_id");
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Unknown action']);
