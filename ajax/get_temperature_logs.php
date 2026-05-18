<?php
/*
 * ============================================================
 *  Get Temperature Logs for Chart Auto-Refresh
 *  File: /GHOST/ajax/get_temperature_logs.php
 *  
 *  Returns latest temperature and humidity logs for a specific
 *  incubator to update the dashboard temperature chart via AJAX
 * ============================================================
 */

require_once '../includes/config.php';
requireUser();

header('Content-Type: application/json');

$incubator_id = (int)($_GET['incubator_id'] ?? 0);
$limit        = (int)($_GET['limit'] ?? 10);

if (!$incubator_id) {
    echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
    exit;
}

// Verify user has access to this incubator
$pdo = getDB();
$uid = $_SESSION['user_id'];

$check = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE incubator_id=? AND user_id=?");
$check->execute([$incubator_id, $uid]);
if (!$check->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get latest temperature logs
$stmt = $pdo->prepare(
    "SELECT temperature, humidity, DATE_FORMAT(recorded_at,'%H:%i') AS lbl
     FROM temperature_logs 
     WHERE incubator_id = ?
     ORDER BY recorded_at DESC 
     LIMIT ?
     "
);
$stmt->bindParam(1, $incubator_id, PDO::PARAM_INT);
$stmt->bindParam(2, $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode($logs);
?>
