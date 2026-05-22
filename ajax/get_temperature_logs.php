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

$check = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incubators i
     WHERE i.id = ?
       AND (
            i.owner_id = ?
            OR EXISTS (
                SELECT 1 FROM batches b
                WHERE b.incubator_id = i.id AND b.user_id = ?
            )
       )"
);
$check->execute([$incubator_id, $uid, $uid]);
if (!$check->fetchColumn()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get latest temperature logs (filter out invalid readings: temp must be > 0 and not NULL)
$stmt = $pdo->prepare(
    "SELECT temperature, humidity, recorded_at, DATE_FORMAT(recorded_at,'%H:%i') AS lbl
     FROM temperature_logs 
     WHERE incubator_id = ? AND temperature > 0 AND temperature IS NOT NULL
     ORDER BY recorded_at DESC 
     LIMIT ?
     "
);
$stmt->bindParam(1, $incubator_id, PDO::PARAM_INT);
$stmt->bindParam(2, $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lastLogAt = $logs && isset($logs[0]['recorded_at']) ? $logs[0]['recorded_at'] : null;
$logs = array_reverse($logs);

// If idle mode is only posting heartbeats, append the latest live reading
$stateStmt = $pdo->prepare(
    "SELECT current_temp, current_humidity, last_seen
     FROM hardware_state
     WHERE incubator_id = ?
     LIMIT 1"
);
$stateStmt->execute([$incubator_id]);
$state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stateTemp = isset($state['current_temp']) ? (float)$state['current_temp'] : null;
$stateHum = isset($state['current_humidity']) ? (float)$state['current_humidity'] : null;
$stateSeen = $state['last_seen'] ?? null;

// Only append live state if temperatures are valid (> 0 and not NULL)
if ($stateSeen && $stateTemp !== null && $stateTemp > 0) {
    $stateTs = strtotime($stateSeen);
    $lastTs = $lastLogAt ? strtotime($lastLogAt) : 0;
    if ($stateTs && $stateTs > $lastTs) {
        $logs[] = [
            'temperature' => $stateTemp,
            'humidity' => $stateHum,
            'lbl' => date('H:i', $stateTs)
        ];
    }
}

foreach ($logs as &$row) {
    $row['temperature'] = isset($row['temperature']) && $row['temperature'] !== null ? (float)$row['temperature'] : null;
    $row['humidity'] = isset($row['humidity']) && $row['humidity'] !== null ? (float)$row['humidity'] : null;
    $row['lbl'] = (string)($row['lbl'] ?? '');
}
unset($row);

echo json_encode($logs);
?>
