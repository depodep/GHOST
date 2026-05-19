<?php
/**
 * ============================================================
 *  GHOST Incubator — Device Heartbeat Endpoint
 *  File: /GHOST/ajax/heartbeat.php
 *
 *  Called by ESP8266 device on regular intervals to:
 *  - Register presence (update last_seen)
 *  - Post current sensor readings
 *  - Request control command
 *
 *  Request:
 *    POST /ajax/heartbeat.php
 *    {
 *      "action": "heartbeat",
 *      "token": "ghost_hw_secret_2024",
 *      "incubator_id": 1,
 *      "temperature": 37.5,
 *      "humidity": 60.2,
 *      "relay_states": { "heater": 1, "swing": 1, ... }
 *    }
 *
 *  Response:
 *    {
 *      "success": true,
 *      "status": "RUN|STOP|COOLDOWN",
 *      "session_id": 123,
 *      "command": { ... },
 *      "next_heartbeat_secs": 30
 *    }
 * ============================================================
 */

require_once '../includes/config.php';
require_once '../includes/HeartbeatScheduler.php';

define('DEVICE_TOKEN', 'ghost_hw_secret_2024');
define('HEARTBEAT_INTERVAL_SECS', 30);

header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';
$token = $input['token'] ?? '';

// Auth
if ($token !== DEVICE_TOKEN) {
    http_response_code(401);
    json_response(['success' => false, 'message' => 'Unauthorized']);
}

if ($action !== 'heartbeat') {
    http_response_code(400);
    json_response(['success' => false, 'message' => 'Invalid action']);
}

$incubator_id = (int)($input['incubator_id'] ?? 0);
$current_temp = isset($input['temperature']) ? (float)$input['temperature'] : null;
$current_humidity = isset($input['humidity']) ? (float)$input['humidity'] : null;
$relay_states = $input['relay_states'] ?? [];

if (!$incubator_id) {
    http_response_code(400);
    json_response(['success' => false, 'message' => 'Missing incubator_id']);
}

try {
    $pdo = getDB();

    // Log sensor reading
    if ($current_temp !== null) {
        $stmt = $pdo->prepare(
            "INSERT INTO temperature_logs 
             (incubator_id, temperature, humidity, recorded_by)
             VALUES (?, ?, ?, 'auto')"
        );
        $stmt->execute([$incubator_id, $current_temp, $current_humidity]);
    }

    // Run scheduler
    $scheduler = new HeartbeatScheduler($pdo, $incubator_id, $current_temp, $current_humidity);
    $result = $scheduler->handleHeartbeat();

    // Add next heartbeat interval
    $result['next_heartbeat_secs'] = HEARTBEAT_INTERVAL_SECS;

    json_response($result);

} catch (Exception $e) {
    error_log("[Heartbeat] Error: " . $e->getMessage());
    http_response_code(500);
    json_response([
        'success' => false,
        'message' => 'Internal server error',
        'status' => 'STOP',
        'error' => $e->getMessage()
    ]);
}

function json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
