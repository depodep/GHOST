<?php
/*
 * ============================================================
 *  GHOST Incubator — Hardware API
 *  File: /GHOST/ajax/hardware_api.php
 *
 *  Called by:
 *    - ESP8266 firmware  (device token auth)
 *    - Admin dashboard   (device token passed from JS)
 *    - User dashboard    (device token passed from JS)
 *
 *  Actions handled:
 *    get_settings         — ESP8266 fetches temp/humidity targets
 *    get_device_config    — ESP8266 fetches config via alias
 *    get_pending_schedules — ESP8266 fetches due turning schedules
 *    mark_done            — ESP8266 marks schedule complete
 *    log_sensor           — ESP8266 posts sensor readings
 *    device_status        — ESP8266 posts fast live heartbeat/status
 *    device_heartbeat     — ESP8266 posts fast live heartbeat/status
 *    device_logs          — ESP8266 posts periodic sensor logs
 *    manual_command       — Dashboard sends heater/swing override
 *    get_live_status      — Dashboard polls latest hardware state
 *    get_device_session   — ESP8266 fetches session window/status
 * ============================================================
 */

require_once '../includes/config.php';
require_once '../includes/ScheduleChecker.php';

// ── Auth — both ESP8266 and dashboard use this token ─────────
define('DEVICE_TOKEN', 'ghost_hw_secret_2024');

header('Content-Type: application/json');

$action = isset($_POST['action'])
    ? trim($_POST['action'])
    : (isset($_GET['action']) ? trim($_GET['action']) : '');
$isTestModeAction = strpos($action, 'test_mode_') === 0;

$token = isset($_POST['token'])
    ? trim($_POST['token'])
    : (isset($_GET['token']) ? trim($_GET['token']) : '');

if (!$isTestModeAction && $token !== DEVICE_TOKEN) {
    json_response(['success' => false, 'message' => 'Unauthorized']);
}

// Start output buffering to prevent accidental output (whitespace/PHP warnings)
if (!ob_get_level()) ob_start();

function json_response($data) {
    // Clean any buffered output that might break JSON
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$pdo    = getDB();

function ensureHardwareStateSchema(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hardware_state (
            incubator_id INT NOT NULL PRIMARY KEY,
            temperature DECIMAL(5,2) DEFAULT NULL,
            humidity DECIMAL(5,2) DEFAULT NULL,
            device_status ENUM('online','offline') NOT NULL DEFAULT 'offline',
            current_temp DECIMAL(5,2) DEFAULT NULL,
            current_humidity DECIMAL(5,2) DEFAULT NULL,
            heater_on TINYINT(1) NOT NULL DEFAULT 0,
            heater_1_status TINYINT(1) NOT NULL DEFAULT 0,
            heater_2_status TINYINT(1) NOT NULL DEFAULT 0,
            heater_fan_status TINYINT(1) NOT NULL DEFAULT 0,
            exhaust_status TINYINT(1) NOT NULL DEFAULT 0,
            swing_on TINYINT(1) NOT NULL DEFAULT 0,
            swing_status TINYINT(1) NOT NULL DEFAULT 0,
            last_seen TIMESTAMP NULL DEFAULT NULL,
            last_active_at TIMESTAMP NULL DEFAULT NULL,
            last_server_sync TIMESTAMP NULL DEFAULT NULL,
            last_egg_turn_at DATETIME DEFAULT NULL,
            running_ops VARCHAR(255) DEFAULT NULL,
            wifi_status ENUM('connected','disconnected') NOT NULL DEFAULT 'connected',
            current_mode ENUM('idle','incubating','hatching') NOT NULL DEFAULT 'idle',
            active_session_name VARCHAR(150) DEFAULT NULL,
            swing_duration_sec INT NOT NULL DEFAULT 30,
            session_started_at DATETIME DEFAULT NULL,
            session_ends_at DATETIME DEFAULT NULL,
            session_completed_at DATETIME DEFAULT NULL,
            session_status ENUM('idle','running','completed') NOT NULL DEFAULT 'idle',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    // Ensure hardware_commands table exists so manual_command/get_pending_command work
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hardware_commands (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            incubator_id INT NOT NULL,
            command VARCHAR(64) NOT NULL,
            issued_at DATETIME NOT NULL,
            INDEX (incubator_id),
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );

    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM hardware_state")->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    if (!isset($columns['session_started_at'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN session_started_at DATETIME DEFAULT NULL AFTER swing_duration_sec");
    }
    if (!isset($columns['session_ends_at'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN session_ends_at DATETIME DEFAULT NULL AFTER session_started_at");
    }
    if (!isset($columns['session_completed_at'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN session_completed_at DATETIME DEFAULT NULL AFTER session_ends_at");
    }
    if (!isset($columns['session_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN session_status ENUM('idle','running','completed') NOT NULL DEFAULT 'idle' AFTER session_completed_at");
    }
    if (!isset($columns['device_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN device_status ENUM('online','offline') NOT NULL DEFAULT 'offline' AFTER humidity");
    }
    if (!isset($columns['current_temp'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN current_temp DECIMAL(5,2) DEFAULT NULL AFTER device_status");
    }
    if (!isset($columns['current_humidity'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN current_humidity DECIMAL(5,2) DEFAULT NULL AFTER current_temp");
    }
    if (!isset($columns['heater_1_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN heater_1_status TINYINT(1) NOT NULL DEFAULT 0 AFTER heater_on");
    }
    if (!isset($columns['heater_2_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN heater_2_status TINYINT(1) NOT NULL DEFAULT 0 AFTER heater_1_status");
    }
    if (!isset($columns['heater_fan_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN heater_fan_status TINYINT(1) NOT NULL DEFAULT 0 AFTER heater_2_status");
    }
    if (!isset($columns['exhaust_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN exhaust_status TINYINT(1) NOT NULL DEFAULT 0 AFTER heater_fan_status");
    }
    if (!isset($columns['swing_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN swing_status TINYINT(1) NOT NULL DEFAULT 0 AFTER swing_on");
    }
    if (!isset($columns['last_active_at'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN last_active_at TIMESTAMP NULL DEFAULT NULL AFTER last_seen");
    }
    if (!isset($columns['last_server_sync'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN last_server_sync TIMESTAMP NULL DEFAULT NULL AFTER last_active_at");
    }
    if (!isset($columns['last_egg_turn_at'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN last_egg_turn_at DATETIME DEFAULT NULL AFTER last_server_sync");
    }
    if (!isset($columns['running_ops'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN running_ops VARCHAR(255) DEFAULT NULL AFTER last_egg_turn_at");
    }
    if (!isset($columns['wifi_status'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN wifi_status ENUM('connected','disconnected') NOT NULL DEFAULT 'connected' AFTER running_ops");
    }
    if (!isset($columns['current_mode'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN current_mode ENUM('idle','incubating','hatching') NOT NULL DEFAULT 'idle' AFTER wifi_status");
    }
    if (!isset($columns['active_session_name'])) {
        $pdo->exec("ALTER TABLE hardware_state ADD COLUMN active_session_name VARCHAR(150) DEFAULT NULL AFTER current_mode");
    }
}

function buildRunningOps(array $state) {
    $ops = [];
    if (!empty($state['heater_on'])) $ops[] = 'heating';
    if (!empty($state['swing_on'])) $ops[] = 'turning';
    if (!empty($state['wifi_status']) && $state['wifi_status'] === 'disconnected') $ops[] = 'wifi_reconnect';
    if (!empty($state['current_mode']) && $state['current_mode'] === 'hatching') $ops[] = 'hatching';
    return empty($ops) ? 'idle' : implode(', ', $ops);
}

function ensureBatchesSchema(PDO $pdo) {
    // Ensure batches table has 'scheduled' status in enum
    try {
        // Check current enum values
        $result = $pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_NAME='batches' AND COLUMN_NAME='status'"
        )->fetch();
        
        if ($result && strpos($result['COLUMN_TYPE'], 'scheduled') === false) {
            // Alter the enum to include 'scheduled'
            $pdo->exec(
                "ALTER TABLE batches MODIFY status ENUM('scheduled','incubating','completed','terminated','hatched','failed','cancelled') DEFAULT 'incubating'"
            );
        }
    } catch (Exception $e) {
        // If check fails, try to alter anyway (may already be correct)
        try {
            $pdo->exec(
                "ALTER TABLE batches MODIFY status ENUM('scheduled','incubating','completed','terminated','hatched','failed','cancelled') DEFAULT 'incubating'"
            );
        } catch (Exception $ignored) {
            // Silently fail if already updated
        }
    }
}

function refreshScheduleState(PDO $pdo, int $incubator_id): array {
    $checker = new ScheduleChecker($pdo, 10);
    $checkerChanges = $checker->processPendingSchedules($incubator_id);

    ensureBatchesSchema($pdo);
    $scheduleGraceSeconds = 10; // 10 seconds
    $changes = [
        'failed_batches' => 0,
        'promoted_batches' => 0,
        'failed_schedules' => 0,
        'promoted_schedules' => 0,
        'started_sessions' => 0,
        'waiting_schedules' => 0
    ];

    foreach ($checkerChanges as $key => $value) {
        if (!isset($changes[$key])) {
            $changes[$key] = 0;
        }
        $changes[$key] += (int)$value;
    }

    $failedScheduleStmt = $pdo->prepare(
                "SELECT s.id, s.batch_id, s.scheduled_date, s.scheduled_time
         FROM schedules s
         LEFT JOIN batches b ON b.id = s.batch_id
         WHERE s.incubator_id = ?
           AND s.status = 'pending'
           AND CONCAT(s.scheduled_date, ' ', s.scheduled_time) < NOW()
           AND (s.batch_id IS NULL OR b.status <> 'incubating')
         ORDER BY s.scheduled_date ASC, s.scheduled_time ASC"
    );
    $failedScheduleStmt->execute([$incubator_id]);
    $overdueSchedules = $failedScheduleStmt->fetchAll();
    $nowTs = time();

    foreach ($overdueSchedules as $schedule) {
        $scheduledAtTs = strtotime(trim(($schedule['scheduled_date'] ?? '') . ' ' . ($schedule['scheduled_time'] ?? '00:00:00')));
        if ($scheduledAtTs !== false && ($scheduledAtTs + $scheduleGraceSeconds) > $nowTs) {
            // Still within grace window, do not fail immediately.
            continue;
        }

        $updated = $pdo->prepare(
            "UPDATE schedules SET status = 'failed' WHERE id = ? AND status = 'pending'"
        )->execute([$schedule['id']]);

        if ($updated !== false) {
            $changes['failed_schedules']++;
        }

        if (!empty($schedule['batch_id'])) {
            $batchUpdated = $pdo->prepare(
                "UPDATE batches SET status = 'failed' WHERE id = ? AND status IN ('scheduled', 'incubating')"
            )->execute([(int)$schedule['batch_id']]);

            if ($batchUpdated !== false) {
                $changes['failed_batches']++;
            }
        }
    }

    $failedStartCheckStmt = $pdo->prepare(
        "SELECT id, batch_name, notes
         FROM batches
         WHERE incubator_id = ? AND status = 'scheduled'
         ORDER BY created_at ASC"
    );
    $failedStartCheckStmt->execute([$incubator_id]);
    $allScheduledBatches = $failedStartCheckStmt->fetchAll();

    $now = new DateTime('now');
    foreach ($allScheduledBatches as $sch) {
        $notes = $sch['notes'] ?? '';
        $scheduledStartTime = null;
        if (preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $notes, $matches)) {
            $scheduledStartTime = $matches[1];
        }

        if ($scheduledStartTime) {
            try {
                $startDateTime = new DateTime($scheduledStartTime);
                $secondsSinceScheduled = ($now->getTimestamp() - $startDateTime->getTimestamp());
                if ($secondsSinceScheduled > $scheduleGraceSeconds) {
                    $pdo->prepare(
                        "UPDATE batches SET status = 'failed' WHERE id = ? AND status = 'scheduled'"
                    )->execute([$sch['id']]);
                    $changes['failed_batches']++;

                    $updated = $pdo->prepare("UPDATE schedules SET status = 'failed' WHERE batch_id = ? AND status = 'pending'")
                        ->execute([$sch['id']]);
                    if ($updated !== false) {
                        $changes['failed_schedules']++;
                    }
                }
            } catch (Exception $e) {
                error_log("[Hardware API] Error parsing scheduled start time for batch {$sch['id']}: " . $e->getMessage());
            }
        }
    }

    return $changes;
}

function deriveHeaterGroupState(array $state) {
    $heaterFan = !empty($state['heater_fan_status']);
    $heater1 = !empty($state['heater_1_status']);
    $heater2 = !empty($state['heater_2_status']);
    $heaterGroup = !empty($state['heater_on']);

    return $heaterGroup || $heater1 || $heater2;
}

function deriveCurrentMode(array $state) {
    if (!empty($state['session_status']) && $state['session_status'] === 'running') {
        return 'incubating';
    }
    if (!empty($state['session_status']) && $state['session_status'] === 'completed') {
        return 'hatching';
    }
    return 'idle';
}

function calculateTurningLockdownState(array $state) {
    $result = [
        'turning_lockdown_active' => false,
        'turning_lockdown_days_remaining' => null
    ];

    if (($state['session_status'] ?? '') !== 'running' || empty($state['session_ends_at'])) {
        return $result;
    }

    $endTs = strtotime($state['session_ends_at']);
    if ($endTs === false) {
        return $result;
    }

    $secondsRemaining = $endTs - time();
    if ($secondsRemaining <= 0) {
        $result['turning_lockdown_active'] = true;
        $result['turning_lockdown_days_remaining'] = 0;
        return $result;
    }

    $daysRemaining = (int)ceil($secondsRemaining / 86400);
    $result['turning_lockdown_days_remaining'] = $daysRemaining;
    $result['turning_lockdown_active'] = $daysRemaining <= 3;

    return $result;
}

function upsertHardwareState(PDO $pdo, $incubator_id, array $state) {
    $now = (string)$pdo->query("SELECT NOW()")->fetchColumn();
    if ($now === '') {
        $now = date('Y-m-d H:i:s');
    }
    $defaults = [
        'temperature' => null,
        'humidity' => null,
        'device_status' => 'online',
        'current_temp' => null,
        'current_humidity' => null,
        'heater_on' => 0,
        'heater_1_status' => 0,
        'heater_2_status' => 0,
        'heater_fan_status' => 0,
        'exhaust_status' => 0,
        'swing_on' => 0,
        'swing_status' => 0,
        'last_seen' => null,
        'last_active_at' => $now,
        'last_server_sync' => $now,
        'last_egg_turn_at' => null,
        'running_ops' => 'idle',
        'wifi_status' => 'connected',
        'current_mode' => 'idle',
        'active_session_name' => null,
        'swing_duration_sec' => 30,
        'session_started_at' => null,
        'session_ends_at' => null,
        'session_completed_at' => null,
        'session_status' => 'idle'
    ];
    $state = array_merge($defaults, $state);

    $stmt = $pdo->prepare(
        "INSERT INTO hardware_state
            (incubator_id, temperature, humidity, device_status, current_temp, current_humidity,
             heater_on, heater_1_status, heater_2_status, heater_fan_status, exhaust_status,
             swing_on, swing_status, last_seen, last_active_at, last_server_sync, last_egg_turn_at,
             running_ops, wifi_status, current_mode, active_session_name, swing_duration_sec,
             session_started_at, session_ends_at, session_completed_at, session_status)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            temperature = VALUES(temperature),
            humidity = VALUES(humidity),
            device_status = VALUES(device_status),
            current_temp = VALUES(current_temp),
            current_humidity = VALUES(current_humidity),
            heater_on = VALUES(heater_on),
            heater_1_status = VALUES(heater_1_status),
            heater_2_status = VALUES(heater_2_status),
            heater_fan_status = VALUES(heater_fan_status),
            exhaust_status = VALUES(exhaust_status),
            swing_on = VALUES(swing_on),
            swing_status = VALUES(swing_status),
            last_seen = COALESCE(VALUES(last_seen), last_seen),
            last_active_at = VALUES(last_active_at),
            last_server_sync = VALUES(last_server_sync),
            last_egg_turn_at = VALUES(last_egg_turn_at),
            running_ops = VALUES(running_ops),
            wifi_status = VALUES(wifi_status),
            current_mode = VALUES(current_mode),
            active_session_name = VALUES(active_session_name),
            swing_duration_sec = VALUES(swing_duration_sec),
            session_started_at = VALUES(session_started_at),
            session_ends_at = VALUES(session_ends_at),
            session_completed_at = VALUES(session_completed_at),
            session_status = VALUES(session_status)"
    );

    $stmt->execute([
        $incubator_id,
        $state['temperature'],
        $state['humidity'],
        $state['device_status'],
        $state['current_temp'],
        $state['current_humidity'],
        (int)$state['heater_on'],
        (int)$state['heater_1_status'],
        (int)$state['heater_2_status'],
        (int)$state['heater_fan_status'],
        (int)$state['exhaust_status'],
        (int)$state['swing_on'],
        (int)$state['swing_status'],
        $state['last_seen'],
        $state['last_active_at'],
        $state['last_server_sync'],
        $state['last_egg_turn_at'],
        $state['running_ops'],
        $state['wifi_status'],
        $state['current_mode'],
        $state['active_session_name'],
        (int)$state['swing_duration_sec'],
        $state['session_started_at'],
        $state['session_ends_at'],
        $state['session_completed_at'],
        $state['session_status']
    ]);
}

function getActiveIncubatingBatch(PDO $pdo, $incubator_id) {
    $stmt = $pdo->prepare(
        "SELECT batch_name, start_date, expected_hatch_date
         FROM batches
         WHERE incubator_id = ?
           AND status = 'incubating'
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$incubator_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchLiveState(PDO $pdo, $incubator_id) {
    $stmt = $pdo->prepare(
        "SELECT hs.*, 
                COALESCE(hs.current_temp, hs.temperature) AS display_temp,
                COALESCE(hs.current_humidity, hs.humidity) AS display_humidity,
                (SELECT b.batch_name
                   FROM batches b
                  WHERE b.incubator_id = hs.incubator_id
                    AND b.status = 'incubating'
                  ORDER BY b.created_at DESC
                  LIMIT 1) AS batch_session_name
         FROM hardware_state hs
         WHERE hs.incubator_id = ?
         LIMIT 1"
    );
    $stmt->execute([$incubator_id]);
    return $stmt->fetch();
}

ensureHardwareStateSchema($pdo);

// ════════════════════════════════════════════
//  1. GET SETTINGS
//     ESP8266 fetches temperature/humidity targets on boot and every 30 s
// ════════════════════════════════════════════
if ($action === 'get_settings') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT ts.target_temp, ts.min_temp, ts.max_temp,
                ts.target_humidity, ts.min_humidity, ts.max_humidity,
                ts.turning_interval, COALESCE(hs.swing_duration_sec,30) AS swing_duration_sec
         FROM temperature_settings ts LEFT JOIN hardware_state hs ON hs.incubator_id=ts.incubator_id
         WHERE ts.incubator_id = ?
         LIMIT 1"
    );
    $stmt->execute([$incubator_id]);
    $row = $stmt->fetch();

    if (!$row) {
        // Safe defaults if no settings row exists
        json_response([
                'success'          => true,
                'target_temp'      => 37.50,
                'min_temp'         => 37.00,
                'max_temp'         => 38.00,
                'target_humidity'  => 55.00,
                'min_humidity'     => 50.00,
                'max_humidity'     => 60.00,
                'target_hum'       => 55.00,
                'min_hum'          => 50.00,
                'max_hum'          => 60.00,
                'turning_interval'  => 8,
                'swing_duration_sec' => 30
            ]);
    }

    $row['target_hum'] = $row['target_humidity'];
    $row['min_hum'] = $row['min_humidity'];
    $row['max_hum'] = $row['max_humidity'];

    echo json_encode(array_merge(['success' => true], $row));
    exit;
}

// ════════════════════════════════════════════
//  2. GET PENDING SCHEDULES
//     ESP8266 polls every 30 s for turning schedules due today
// ════════════════════════════════════════════
if ($action === 'get_pending_schedules') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, action_type, scheduled_date, scheduled_time, title
         FROM schedules
         WHERE incubator_id   = ?
           AND action_type    = 'turning'
           AND status         = 'pending'
           AND scheduled_date = CURDATE()
           AND scheduled_time <= CURTIME()
         ORDER BY scheduled_time ASC"
    );
    $stmt->execute([$incubator_id]);
    $schedules = $stmt->fetchAll();

    json_response(['success' => true, 'schedules' => $schedules]);
}

// ════════════════════════════════════════════
//  3. MARK SCHEDULE DONE
//     ESP8266 calls this after completing a swing cycle
// ════════════════════════════════════════════
if ($action === 'mark_done') {
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
    if (!$schedule_id) {
        echo json_encode(['success' => false, 'message' => 'Missing schedule_id']);
        exit;
    }

    $pdo->prepare("UPDATE schedules SET status = 'done' WHERE id = ?")
        ->execute([$schedule_id]);

    json_response(['success' => true]);
}

// ════════════════════════════════════════════
//  4. FAST STATUS / HEARTBEAT
//     ESP8266 posts live values every few seconds
// ════════════════════════════════════════════
if ($action === 'device_status' || $action === 'device_heartbeat') {
    $incubator_id = (int)($_POST['incubator_id']  ?? 0);
    if (!$incubator_id) {
        json_response(['success' => false, 'message' => 'Invalid data']);
    }

    refreshScheduleState($pdo, $incubator_id);

    $temperature    = isset($_POST['temperature']) ? (float)$_POST['temperature'] : null;
    $humidity       = isset($_POST['humidity']) ? (float)$_POST['humidity'] : null;
    
    $wifi_connected = (int)($_POST['wifi_connected'] ?? 1);
    $running_ops    = trim((string)($_POST['running_ops'] ?? ''));
    $current_mode   = trim((string)($_POST['current_mode'] ?? ''));
    $activeSession  = isset($_POST['active_session_name']) ? trim($_POST['active_session_name']) : null;
    $lastEggTurnAt  = isset($_POST['last_egg_turn_at']) ? trim($_POST['last_egg_turn_at']) : null;
    $lastSwingExecutedMs = isset($_POST['last_swing_executed_at']) ? (int)$_POST['last_swing_executed_at'] : 0;
    $previousState  = fetchLiveState($pdo, $incubator_id);
    $activeBatch = getActiveIncubatingBatch($pdo, $incubator_id);
    $shouldPersistRelayState = $current_mode !== '' && $current_mode !== 'idle';

    // If the server recently marked the session completed (e.g. Stop was pressed),
    // ignore a subsequent heartbeat that posts current_mode='incubating' so the
    // device doesn't immediately re-enable the session on the server. This
    // prevents a race where the device posts old state right after Stop was pressed.
    $recentlyCompleted = false;
    if ($previousState && !empty($previousState['session_completed_at'])) {
        $secs = time() - strtotime($previousState['session_completed_at']);
        if ($secs >= 0 && $secs < 120) { // 2 minutes grace
            $recentlyCompleted = true;
        }
    }
    if (!$activeBatch && $recentlyCompleted && strtolower($current_mode) === 'incubating') {
        error_log("[hardware_api] Ignoring device 'incubating' mode due to recent server stop (incubator={$incubator_id})");
        $current_mode = 'idle';
    }

    // Parse individual relay states from POST data.
    // In IDLE mode, we DO process the relay states from ESP8266 to properly turn off fans/heaters.
    // Only preserve old state in test mode (test_mode_relay_status table check handles this separately).
    $heater_on      = (int)($_POST['heater'] ?? ($_POST['heater_on'] ?? 0));
    $heater_1       = (int)($_POST['h1'] ?? ($_POST['heater_1'] ?? ($_POST['heater_1_status'] ?? 0)));
    $heater_2       = (int)($_POST['h2'] ?? ($_POST['heater_2'] ?? ($_POST['heater_2_status'] ?? 0)));
    $heater_fan     = (int)($_POST['fan'] ?? ($_POST['heater_fan'] ?? ($_POST['heater_fan_status'] ?? 0)));
    $exhaust        = (int)($_POST['exhaust'] ?? ($_POST['exhaust_status'] ?? 0));
    $swing_on       = (int)($_POST['swing']  ?? ($_POST['swing_on'] ?? 0));
    if ($shouldPersistRelayState && !$heater_on && ($heater_fan || $heater_1 || $heater_2)) {
        $heater_on = 1;
    }

    if ($activeBatch && !$current_mode) {
        $current_mode = 'incubating';
    }

    // Use last swing executed time from ESP8266 if available
    if ($lastSwingExecutedMs > 0) {
        $lastEggTurnAt = date('Y-m-d H:i:s', (int)($lastSwingExecutedMs / 1000));
    } elseif ($swing_on && (!$previousState || empty($previousState['swing_status']))) {
        $lastEggTurnAt = date('Y-m-d H:i:s');
    }

    if ($activeBatch && empty($activeSession)) {
        $activeSession = $activeBatch['batch_name'];
    }

    $sessionStatus = $activeBatch ? 'running' : ($current_mode === 'incubating' ? 'running' : 'idle');

    $dbNow = (string)$pdo->query("SELECT NOW()")->fetchColumn();

    upsertHardwareState($pdo, $incubator_id, [
        'temperature' => $temperature,
        'humidity' => $humidity,
        'device_status' => $wifi_connected ? 'online' : 'offline',
        'current_temp' => $temperature,
        'current_humidity' => $humidity,
        'heater_on' => $heater_on,
        'heater_1_status' => $heater_1,
        'heater_2_status' => $heater_2,
        'heater_fan_status' => $heater_fan,
        'exhaust_status' => $exhaust,
        'swing_on' => $swing_on,
        'swing_status' => $swing_on,
        'last_seen' => $dbNow,
        'last_active_at' => $dbNow,
        'last_server_sync' => $dbNow,
        'last_egg_turn_at' => $lastEggTurnAt ?: null,
        'running_ops' => $running_ops !== '' ? $running_ops : buildRunningOps([
            'heater_on' => $heater_on,
            'swing_on' => $swing_on,
            'wifi_status' => $wifi_connected ? 'connected' : 'disconnected',
            'current_mode' => $current_mode
        ]),
        'wifi_status' => $wifi_connected ? 'connected' : 'disconnected',
        'current_mode' => $current_mode !== '' ? $current_mode : 'idle',
        'active_session_name' => $activeSession,
        'session_status' => $sessionStatus
    ]);

    json_response(['success' => true]);
}

// ════════════════════════════════════════════
//  4b. LOG SENSOR DATA
//     ESP8266 posts readings every 60 s
//     Inserts into temperature_logs and updates live state
// ════════════════════════════════════════════
if ($action === 'log_sensor' || $action === 'device_logs') {
    $incubator_id = (int)(isset($_POST['incubator_id']) ? $_POST['incubator_id'] : 0);
    $temperature  = isset($_POST['temperature']) ? (float)$_POST['temperature'] : 0;
    $humidity     = isset($_POST['humidity']) ? (float)$_POST['humidity'] : null;
    
    $wifi_connected = (int)(isset($_POST['wifi_connected']) ? $_POST['wifi_connected'] : 1);
    $running_ops  = trim((string)(isset($_POST['running_ops']) ? $_POST['running_ops'] : ''));
    $current_mode = trim((string)(isset($_POST['current_mode']) ? $_POST['current_mode'] : ''));
    $lastSwingExecutedMs = isset($_POST['last_swing_executed_at']) ? (int)$_POST['last_swing_executed_at'] : 0;
    $previousState = fetchLiveState($pdo, $incubator_id);
    $activeBatch = getActiveIncubatingBatch($pdo, $incubator_id);
    $sessionStatus = $activeBatch ? 'running' : ($current_mode === 'incubating' ? 'running' : 'idle');

    if ($activeBatch && !$current_mode) {
        $current_mode = 'incubating';
    }

    if (!$incubator_id || $temperature == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    // Parse individual relay states (ESP8266 sends: heater, h1/heater_1, h2/heater_2, fan/heater_fan, swing, exhaust)
    // If not explicitly sent, preserve the previous state (don't default to main heater value)
    $heater_on    = (int)(isset($_POST['heater']) ? $_POST['heater'] : (isset($_POST['heater_on']) ? $_POST['heater_on'] : 0));
    $heater_1     = (int)(isset($_POST['h1']) ? $_POST['h1'] : (isset($_POST['heater_1']) ? $_POST['heater_1'] : (isset($_POST['heater_1_status']) ? $_POST['heater_1_status'] : (isset($previousState['heater_1_status']) ? $previousState['heater_1_status'] : 0))));
    $heater_2     = (int)(isset($_POST['h2']) ? $_POST['h2'] : (isset($_POST['heater_2']) ? $_POST['heater_2'] : (isset($_POST['heater_2_status']) ? $_POST['heater_2_status'] : (isset($previousState['heater_2_status']) ? $previousState['heater_2_status'] : 0))));
    $heater_fan   = (int)(isset($_POST['fan']) ? $_POST['fan'] : (isset($_POST['heater_fan']) ? $_POST['heater_fan'] : (isset($_POST['heater_fan_status']) ? $_POST['heater_fan_status'] : (isset($previousState['heater_fan_status']) ? $previousState['heater_fan_status'] : 0))));
    $exhaust      = (int)(isset($_POST['exhaust']) ? $_POST['exhaust'] : (isset($_POST['exhaust_status']) ? $_POST['exhaust_status'] : (isset($previousState['exhaust_status']) ? $previousState['exhaust_status'] : 0)));
    $swing_on     = (int)(isset($_POST['swing']) ? $_POST['swing'] : (isset($_POST['swing_on']) ? $_POST['swing_on'] : (isset($previousState['swing_on']) ? $previousState['swing_on'] : 0)));
    if (!$heater_on && ($heater_fan || $heater_1 || $heater_2)) {
        $heater_on = 1;
    }

    // Insert temperature log
    $pdo->prepare(
        "INSERT INTO temperature_logs
            (incubator_id, temperature, humidity, recorded_by, recorded_id)
         VALUES (?, ?, ?, 'auto', NULL)"
    )->execute([$incubator_id, $temperature, $humidity]);

    upsertHardwareState($pdo, $incubator_id, [
        'temperature' => $temperature,
        'humidity' => $humidity,
        'device_status' => $wifi_connected ? 'online' : 'offline',
        'current_temp' => $temperature,
        'current_humidity' => $humidity,
        'heater_on' => $heater_on,
        'heater_1_status' => $heater_1,
        'heater_2_status' => $heater_2,
        'heater_fan_status' => $heater_fan,
        'exhaust_status' => $exhaust,
        'swing_on' => $swing_on,
        'swing_status' => $swing_on,
        'last_egg_turn_at' => $lastSwingExecutedMs > 0 
            ? date('Y-m-d H:i:s', (int)($lastSwingExecutedMs / 1000)) 
            : ((!$previousState || empty($previousState['swing_status'])) && $swing_on ? date('Y-m-d H:i:s') : (isset($previousState['last_egg_turn_at']) ? $previousState['last_egg_turn_at'] : null)),
        'running_ops' => $running_ops !== '' ? $running_ops : buildRunningOps([
            'heater_on' => $heater_on,
            'swing_on' => $swing_on,
            'wifi_status' => $wifi_connected ? 'connected' : 'disconnected',
            'current_mode' => $current_mode
        ]),
        'wifi_status' => $wifi_connected ? 'connected' : 'disconnected',
        'current_mode' => $current_mode !== '' ? $current_mode : 'idle',
        'active_session_name' => $activeBatch ? $activeBatch['batch_name'] : (isset($previousState['active_session_name']) ? $previousState['active_session_name'] : null),
        'session_status' => $sessionStatus
    ]);

    // Fetch settings to check thresholds
    $s = $pdo->prepare(
        "SELECT min_temp, max_temp, min_humidity, max_humidity
         FROM temperature_settings ts LEFT JOIN hardware_state hs ON hs.incubator_id=ts.incubator_id WHERE ts.incubator_id = ? LIMIT 1"
    );
    $s->execute([$incubator_id]);
    $settings = $s->fetch();

    if ($settings) {
        $alerts = [];

        if ($temperature > (float)$settings['max_temp']) {
            $alerts[] = ['temp_high', "Hardware: Temperature {$temperature}°C exceeds max {$settings['max_temp']}°C", 'warning'];
        }
        if ($temperature < (float)$settings['min_temp']) {
            $alerts[] = ['temp_low',  "Hardware: Temperature {$temperature}°C below min {$settings['min_temp']}°C",  'danger'];
        }
        if ($humidity !== null) {
            if ($humidity > (float)$settings['max_humidity']) {
                $alerts[] = ['humidity_high', "Hardware: Humidity {$humidity}% exceeds max {$settings['max_humidity']}%", 'warning'];
            }
            if ($humidity < (float)$settings['min_humidity']) {
                $alerts[] = ['humidity_low',  "Hardware: Humidity {$humidity}% below min {$settings['min_humidity']}%",  'warning'];
            }
        }

        if (!empty($alerts)) {
            $ins = $pdo->prepare(
                "INSERT INTO alerts (incubator_id, alert_type, message, severity)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($alerts as $a) {
                $ins->execute([$incubator_id, $a[0], $a[1], $a[2]]);
            }
        }
    }

    json_response(['success' => true]);
}

// ════════════════════════════════════════════
//  4c. GET DEVICE CONFIG
// ════════════════════════════════════════════
if ($action === 'refresh_schedule_state') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        json_response(['success' => false, 'message' => 'Missing incubator_id']);
    }

    $result = refreshScheduleState($pdo, $incubator_id);
    json_response(array_merge(['success' => true, 'message' => 'Schedule state refreshed'], $result));
}

if ($action === 'get_device_config') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        json_response(['success' => false, 'message' => 'Missing incubator_id']);
    }

    // Refresh batch state before returning device config
    refreshScheduleState($pdo, $incubator_id);

    // Get hardware state first to check current session status
    $hwCheckStmt = $pdo->prepare(
        "SELECT session_status, session_started_at, updated_at FROM hardware_state WHERE incubator_id = ? LIMIT 1"
    );
    $hwCheckStmt->execute([$incubator_id]);
    $hwState = $hwCheckStmt->fetch();
    
    // Check for active batch 
    $batchCheckStmt = $pdo->prepare(
        "SELECT id, batch_name, start_date, expected_hatch_date
         FROM batches
         WHERE incubator_id = ? AND status = 'incubating'
         ORDER BY created_at DESC LIMIT 1"
    );
    $batchCheckStmt->execute([$incubator_id]);
    $activeBatch = $batchCheckStmt->fetch();

    $nextBatchStmt = $pdo->prepare(
        "SELECT id, batch_name, notes
         FROM batches
         WHERE incubator_id = ? AND status = 'scheduled'
         ORDER BY created_at ASC
         LIMIT 1"
    );
    $nextBatchStmt->execute([$incubator_id]);
    $nextBatch = $nextBatchStmt->fetch();

    // Attempt to fetch next pending schedule. Wrap in try/catch because some
    // deployments may not have the `duration_hours` column yet (migration
    // missing). If the query fails, fall back to null so the API returns
    // a safe JSON payload instead of a fatal error.
    try {
        $nextScheduleStmt = $pdo->prepare(
            "SELECT duration_hours, description
             FROM schedules
             WHERE incubator_id = ? AND status = 'pending'
             ORDER BY scheduled_date ASC, scheduled_time ASC
             LIMIT 1"
        );
        $nextScheduleStmt->execute([$incubator_id]);
        $nextSchedule = $nextScheduleStmt->fetch();
    } catch (PDOException $e) {
        error_log('[hardware_api] Failed to fetch next schedule: ' . $e->getMessage());
        $nextSchedule = null;
    }

    $scheduledBatchCountStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM batches WHERE incubator_id = ? AND status = 'scheduled'"
    );
    $scheduledBatchCountStmt->execute([$incubator_id]);
    $scheduledBatchCount = (int)$scheduledBatchCountStmt->fetchColumn();

    $nextSessionStartAt = null;
    if ($nextBatch && !empty($nextBatch['notes']) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $nextBatch['notes'], $matches)) {
        $nextSessionStartAt = $matches[1];
    }
    $nextSessionStartEpoch = $nextSessionStartAt ? strtotime($nextSessionStartAt) : null;
    $nextSessionEndsAt = null;
    $nextSessionDurationHours = null;
    if ($nextSchedule && $nextSessionStartEpoch) {
        if (isset($nextSchedule['duration_hours']) && $nextSchedule['duration_hours'] !== null && $nextSchedule['duration_hours'] !== '') {
            $nextSessionDurationHours = (float)$nextSchedule['duration_hours'];
        }
        if (($nextSessionDurationHours === null || $nextSessionDurationHours <= 0) && !empty($nextSchedule['description'])) {
            $decodedDescription = json_decode((string)$nextSchedule['description'], true);
            if (is_array($decodedDescription) && isset($decodedDescription['session_duration_sec'])) {
                $nextSessionDurationHours = max(0, (float)$decodedDescription['session_duration_sec'] / 3600);
            }
        }
        if ($nextSessionDurationHours !== null && $nextSessionDurationHours > 0) {
            $nextSessionEndsAt = date('Y-m-d H:i:s', $nextSessionStartEpoch + (int)round($nextSessionDurationHours * 3600));
        }
    }
    
    // ONLY mark batch as failed if:
    // 1. There IS an active incubating batch
    // 2. Hardware has NOT been heard from in >30 minutes (definitely offline)
    if ($activeBatch && $hwState) {
        $lastUpdateTime = $hwState['updated_at'];
        $secondsSinceUpdate = time() - strtotime($lastUpdateTime);
        
        // Only mark as failed if truly abandoned (>30 min no contact)
        if ($secondsSinceUpdate > 1800) {
            $pdo->prepare(
                "UPDATE batches SET status = 'failed' WHERE id = ? AND status = 'incubating'"
            )->execute([$activeBatch['id']]);
            $activeBatch = null; // Don't report this batch as active
        }
        // If hardware session shows running, keep the batch active regardless of sync status
    }

    $stmt = $pdo->prepare(
        "SELECT ts.target_temp, ts.min_temp, ts.max_temp,
                ts.target_humidity, ts.min_humidity, ts.max_humidity,
                ts.turning_interval, COALESCE(hs.swing_duration_sec,30) AS swing_duration_sec,
                hs.session_status, hs.session_started_at, hs.session_ends_at, hs.active_session_name
         FROM temperature_settings ts
         LEFT JOIN hardware_state hs ON hs.incubator_id = ts.incubator_id
         WHERE ts.incubator_id = ?
         LIMIT 1"
    );
    $stmt->execute([$incubator_id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response([
            'success' => true,
            'target_temp' => 37.50,
            'min_temp' => 37.00,
            'max_temp' => 38.00,
            'target_humidity' => 55.00,
            'min_humidity' => 50.00,
            'max_humidity' => 60.00,
            'target_hum' => 55.00,
            'min_hum' => 50.00,
            'max_hum' => 60.00,
            'turning_interval' => 8,
            'swing_duration_sec' => 30,
            'session_status' => 'idle',
            'session_id' => $activeBatch ? (int)$activeBatch['id'] : 0,
            'active_session_name' => $activeBatch ? $activeBatch['batch_name'] : '',
            'session_name' => $activeBatch ? $activeBatch['batch_name'] : '',
            'next_session_id' => $nextBatch ? (int)$nextBatch['id'] : 0,
            'next_session_name' => $nextBatch ? $nextBatch['batch_name'] : '',
            'next_session_start_at' => $nextSessionStartAt,
            'next_session_start_epoch' => $nextSessionStartEpoch,
            'next_session_ends_at' => $nextSessionEndsAt,
            'next_session_duration_hours' => $nextSessionDurationHours,
            'next_session_status' => $nextBatch ? 'scheduled' : 'idle',
            'scheduled_batch_count' => $scheduledBatchCount
        ]);
    }

    // Override session_name if we found an active batch
    if ($activeBatch && !$row['active_session_name']) {
        $row['active_session_name'] = $activeBatch['batch_name'];
    }

    // If a batch is incubating, keep the session running for device config
    if ($activeBatch && ($row['session_status'] ?? '') !== 'running') {
        $row['session_status'] = 'running';
        if (empty($row['session_started_at']) && !empty($activeBatch['start_date'])) {
            $row['session_started_at'] = $activeBatch['start_date'] . ' 00:00:00';
        }
        if (empty($row['session_ends_at']) && !empty($activeBatch['expected_hatch_date'])) {
            $row['session_ends_at'] = $activeBatch['expected_hatch_date'] . ' 00:00:00';
        }
    }

    $row['target_hum'] = $row['target_humidity'];
    $row['min_hum'] = $row['min_humidity'];
    $row['max_hum'] = $row['max_humidity'];
    $row['session_id'] = $activeBatch ? (int)$activeBatch['id'] : 0;
    $row['active_session_name'] = $row['active_session_name'] ?? '';
    $row['session_name'] = $row['active_session_name'];
    $row['next_session_id'] = $nextBatch ? (int)$nextBatch['id'] : 0;
    $row['next_session_name'] = $nextBatch ? $nextBatch['batch_name'] : '';
    $row['next_session_start_at'] = $nextSessionStartAt;
    $row['next_session_start_epoch'] = $nextSessionStartEpoch;
    $row['next_session_status'] = $nextBatch ? 'scheduled' : 'idle';
    $row['scheduled_batch_count'] = $scheduledBatchCount;
    $row = array_merge($row, calculateTurningLockdownState($row));

    json_response(array_merge(['success' => true], $row));
}

// ════════════════════════════════════════════
//  4d. GET DEVICE SESSION
// ════════════════════════════════════════════
if ($action === 'get_device_session') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        json_response(['success' => false, 'message' => 'Missing incubator_id']);
    }

    $state = fetchLiveState($pdo, $incubator_id);
    if (!$state) {
        json_response([
            'success' => true,
            'session_status' => 'idle',
            'session_started_at' => null,
            'session_ends_at' => null,
            'session_completed_at' => null,
            'current_mode' => 'idle',
            'active_session_name' => null
        ]);
    }

    json_response([
        'success' => true,
        'session_status' => $state['session_status'] ?? 'idle',
        'session_started_at' => $state['session_started_at'] ?? null,
        'session_ends_at' => $state['session_ends_at'] ?? null,
        'session_completed_at' => $state['session_completed_at'] ?? null,
        'current_mode' => $state['current_mode'] ?? deriveCurrentMode($state),
        'active_session_name' => $state['active_session_name'] ?? $state['batch_session_name'] ?? null
    ]);
}

// ════════════════════════════════════════════
//  5. MANUAL COMMAND  (from Admin / User Dashboard)
//     Writes a pending command into hardware_commands table.
//     ESP8266 polls this on every loop and executes it.
//     Commands: heater_on, heater_off, swing_on, swing_off, all_off
// ════════════════════════════════════════════
if ($action === 'manual_command') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $command      = isset($_POST['command']) ? trim($_POST['command']) : '';

    $allowed = ['heater_on', 'heater_off', 'swing_on', 'swing_off', 'all_off'];
    if (!$incubator_id || !in_array($command, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid command or incubator']);
        exit;
    }

    // Clear any old pending command for this incubator, insert new one
    $pdo->prepare("DELETE FROM hardware_commands WHERE incubator_id = ?")
        ->execute([$incubator_id]);

    $pdo->prepare(
        "INSERT INTO hardware_commands (incubator_id, command, issued_at)
         VALUES (?, ?, NOW())"
    )->execute([$incubator_id, $command]);

    // Log the manual action in activity_logs
    $role    = isset($_SESSION['admin_id']) ? 'admin' : 'user';
    $user_id = $role === 'admin'
        ? ($_SESSION['admin_id'] ?? 0)
        : ($_SESSION['user_id']  ?? 0);
    logActivity($role, $user_id, 'manual_hardware_command',
        "Command '{$command}' sent to incubator #{$incubator_id}");

    json_response(['success' => true, 'command' => $command]);
}

if ($action === 'test_manual_command') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $command      = isset($_POST['command']) ? trim($_POST['command']) : '';

    $allowed = ['heater_on', 'heater_off', 'swing_on', 'swing_off', 'all_off'];
    if (!$incubator_id || !in_array($command, $allowed)) {
        json_response(['success' => false, 'message' => 'Invalid command or incubator']);
    }

    $stateStmt = $pdo->prepare("SELECT session_status FROM hardware_state WHERE incubator_id = ? LIMIT 1");
    $stateStmt->execute([$incubator_id]);
    $sessionStatus = (string)($stateStmt->fetchColumn() ?: 'idle');
    if ($sessionStatus === 'running') {
        json_response(['success' => false, 'message' => 'Relay testing is locked while a session is running']);
    }

    $pdo->prepare("DELETE FROM hardware_commands WHERE incubator_id = ?")
        ->execute([$incubator_id]);

    $pdo->prepare(
        "INSERT INTO hardware_commands (incubator_id, command, issued_at)
         VALUES (?, ?, NOW())"
    )->execute([$incubator_id, $command]);

    $role    = isset($_SESSION['admin_id']) ? 'admin' : 'user';
    $user_id = $role === 'admin'
        ? ($_SESSION['admin_id'] ?? 0)
        : ($_SESSION['user_id']  ?? 0);
    logActivity($role, $user_id, 'relay_test_command',
        "Command '{$command}' sent to incubator #{$incubator_id}");

    json_response(['success' => true, 'command' => $command]);
}

// ════════════════════════════════════════════
//  6. GET PENDING COMMAND  (ESP8266 polls every 5 s)
//     Returns the latest unexecuted manual command, then clears it
// ════════════════════════════════════════════
if ($action === 'get_pending_command') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, command FROM hardware_commands
         WHERE incubator_id = ?
         ORDER BY issued_at DESC LIMIT 1"
    );
    $stmt->execute([$incubator_id]);
    $cmd = $stmt->fetch();

    if (!$cmd) {
        json_response(['success' => true, 'command' => null]);
    }

    // Clear it so it only executes once
    $pdo->prepare("DELETE FROM hardware_commands WHERE id = ?")
        ->execute([$cmd['id']]);

    json_response(['success' => true, 'command' => $cmd['command']]);
}

// ════════════════════════════════════════════
//  7. START SESSION
//     Dashboard starts an incubation session and stores the time window.
// ════════════════════════════════════════════
if ($action === 'start_session') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $session_days  = (int)($_POST['session_days'] ?? 0);
    $session_duration_sec = (int)($_POST['session_duration_sec'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 0);
    $duration_hours = (int)($_POST['duration_hours'] ?? 0);
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $duration_seconds = (int)($_POST['duration_seconds'] ?? 0);
    $egg_count     = (int)($_POST['egg_count'] ?? 0);
    $egg_type      = trim((string)($_POST['egg_type'] ?? 'Chicken'));
    $scheduled_start_datetime = trim((string)($_POST['scheduled_start_datetime'] ?? ''));
    $startImmediately = true;

    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }

    // Diagnostic log to help debug session start issues
    error_log(sprintf("[Hardware API] start_session called - incubator_id=%d, egg_count=%d, session_duration_sec=%d, scheduled_start_datetime='%s', _SERVER_REMOTE=%s", $incubator_id, $egg_count, $session_duration_sec, $scheduled_start_datetime, isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'unknown'));

    // Check if a session is already incubating
    $runningStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM hardware_state WHERE incubator_id = ? AND session_status = 'running' LIMIT 1"
    );
    $runningStmt->execute([$incubator_id]);
    if ((int)($runningStmt->fetchColumn() ?: 0) > 0) {
        json_response(['success' => false, 'message' => 'A session is already running on this incubator']);
    }
    
    // Check if a scheduled batch already exists
    $scheduledStmt = $pdo->prepare(
        "SELECT id FROM batches WHERE incubator_id = ? AND status = 'scheduled'"
    );
    $scheduledStmt->execute([$incubator_id]);
    $scheduledBatchIds = array_map('intval', $scheduledStmt->fetchAll(PDO::FETCH_COLUMN, 0));
    if (!empty($scheduledBatchIds)) {
        if ($startImmediately) {
            // Override existing scheduled sessions for immediate start.
            $placeholders = implode(',', array_fill(0, count($scheduledBatchIds), '?'));
            $pdo->prepare("UPDATE batches SET status='cancelled', terminated_at = NOW() WHERE id IN ($placeholders)")
                ->execute($scheduledBatchIds);
            $pdo->prepare("UPDATE schedules SET status='cancelled' WHERE batch_id IN ($placeholders) AND status = 'pending'")
                ->execute($scheduledBatchIds);
            error_log(sprintf("[Hardware API] start_session overriding scheduled batch ids=%s for immediate start", implode(',', $scheduledBatchIds)));
        } else {
            json_response(['success' => false, 'message' => 'A scheduled session already exists. Please cancel it first.']);
        }
    }

    if ($session_duration_sec < 1) {
        if ($duration_days > 0 || $duration_hours > 0 || $duration_minutes > 0 || $duration_seconds > 0) {
            $session_duration_sec = ($duration_days * 86400) + ($duration_hours * 3600) + ($duration_minutes * 60) + $duration_seconds;
        } elseif ($session_days > 0) {
            $session_duration_sec = $session_days * 86400;
        }
    }

    if ($session_duration_sec < 1) {
        $session_duration_sec = 21 * 86400;
    }

    $session_days = (int)ceil($session_duration_sec / 86400);

    $userIdStmt = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : (int)($_SESSION['user_id'] ?? 1);
    $user_id = $userIdStmt > 0 ? $userIdStmt : 1;

    $incubatorStmt = $pdo->prepare("SELECT name FROM incubators WHERE id = ? LIMIT 1");
    $incubatorStmt->execute([$incubator_id]);
    $incubatorName = $incubatorStmt->fetchColumn() ?: ('Incubator #' . $incubator_id);

    $sessionNameStmt = $pdo->prepare(
        "SELECT batch_name
         FROM batches
         WHERE incubator_id = ? AND status = 'incubating'
         ORDER BY created_at DESC
         LIMIT 1"
    );
    $sessionNameStmt->execute([$incubator_id]);
    $activeSessionName = $sessionNameStmt->fetchColumn() ?: null;

    // Parse scheduled start datetime (format: "YYYY-MM-DD HH:MM")
    $scheduledDateTime = null;
    $batchStartDate = date('Y-m-d');
    if (!empty($scheduled_start_datetime)) {
        try {
            $scheduledDateTime = new DateTime($scheduled_start_datetime);
            $batchStartDate = $scheduledDateTime->format('Y-m-d');
            $startImmediately = $scheduledDateTime->getTimestamp() <= time();
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Invalid scheduled_start_datetime format. Use YYYY-MM-DD HH:MM']);
        }
    } else {
        $scheduledDateTime = new DateTime('now');
        $startImmediately = true;
    }

    $batchName = sprintf('%s - %d eggs', $incubatorName, $egg_count);
    $batchHatchDate = date('Y-m-d', strtotime('+' . $session_duration_sec . ' seconds', $scheduledDateTime->getTimestamp()));
    $sessionStartedAt = $scheduledDateTime->format('Y-m-d H:i:s');
    $sessionEndsAt = date('Y-m-d H:i:s', strtotime('+' . $session_duration_sec . ' seconds', $scheduledDateTime->getTimestamp()));
    $sessionStatus = $startImmediately ? 'running' : 'scheduled';
    $batchStatus = $startImmediately ? 'incubating' : 'scheduled';

    // Create NEW batch with status aligned to the requested start time
    $batchStmt = $pdo->prepare(
        "INSERT INTO batches
            (incubator_id, user_id, batch_name, egg_type, egg_count, start_date, expected_hatch_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $batchStmt->execute([$incubator_id, $user_id, $batchName, $egg_type, $egg_count, $batchStartDate, $batchHatchDate, $batchStatus]);
    $batchId = (int)$pdo->lastInsertId();

    error_log(sprintf("[Hardware API] start_session created batch id=%d status=%s user_id=%d", $batchId, $batchStatus, $user_id));

    // Store scheduled start time in notes
    $scheduledStartFormatted = $scheduledDateTime->format('Y-m-d H:i:s');
    $pdo->prepare("UPDATE batches SET notes = CONCAT('SCHEDULED_START: ', ?) WHERE id = ?")
        ->execute([$scheduledStartFormatted, $batchId]);

    if ($startImmediately) {
        $dbNow = (string)$pdo->query("SELECT NOW()")->fetchColumn();

        upsertHardwareState($pdo, $incubator_id, [
            'session_status' => 'running',
            'session_started_at' => $sessionStartedAt,
            'session_ends_at' => $sessionEndsAt,
            'session_completed_at' => null,
            'current_mode' => 'incubating',
            'active_session_name' => $batchName,
            'last_server_sync' => $dbNow,
            'last_active_at' => $dbNow
        ]);

        // Ensure hardware_state is explicitly updated in case upsertHardwareState didn't persist session fields
        $pdo->prepare(
            "UPDATE hardware_state SET session_status = 'running', session_started_at = ?, session_ends_at = ?, session_completed_at = NULL, current_mode = 'incubating', active_session_name = ?, last_server_sync = NOW(), last_active_at = NOW() WHERE incubator_id = ?"
        )->execute([$sessionStartedAt, $sessionEndsAt, $batchName, $incubator_id]);

        $pdo->prepare(
            "UPDATE batches
                SET status = 'incubating', start_date = ?, expected_hatch_date = ?
             WHERE id = ?"
        )->execute([$batchStartDate, date('Y-m-d', strtotime($sessionEndsAt)), $batchId]);
    }

    json_response([
        'success'                  => true,
        'scheduled_start_datetime' => $scheduledStartFormatted,
        'session_status'           => $sessionStatus,
        'batch_id'                 => $batchId,
        'batch_name'               => $batchName,
        'egg_count'                => $egg_count,
        'egg_type'                 => $egg_type,
        'session_days'             => $session_days,
        'session_duration_sec'     => $session_duration_sec,
        'session_started_at'       => $startImmediately ? $sessionStartedAt : null,
        'session_ends_at'          => $startImmediately ? $sessionEndsAt : null,
        'message'                  => $startImmediately
            ? 'Session started at ' . $sessionStartedAt
            : 'Batch scheduled to start at ' . $scheduledStartFormatted
    ]);
}

// ════════════════════════════════════════════════════════════════════
//  7b. STOP SESSION
//     Dashboard can stop an active session early — mark completed and ask hardware to stop
// ════════════════════════════════════════════════════════════════════
if ($action === 'stop_session') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }

    try {
        // Mark session terminated
        $pdo->prepare(
            "UPDATE hardware_state
                SET session_status = 'completed', session_completed_at = NOW(), current_mode = 'idle', last_server_sync = NOW()
             WHERE incubator_id = ?"
        )->execute([$incubator_id]);

        // Also mark associated batch as terminated and reset egg count
        $pdo->prepare(
            "UPDATE batches
                SET status = 'terminated', terminated_at = NOW(), egg_count = 0
             WHERE incubator_id = ? AND status = 'incubating'"
        )->execute([$incubator_id]);

        // Also insert an 'all_off' hardware command so ESP will stop relays promptly
        $pdo->prepare(
            "INSERT INTO hardware_commands (incubator_id, command, issued_at)
             VALUES (?, 'all_off', NOW())"
        )->execute([$incubator_id]);
    } catch (PDOException $e) {
        // Return error and include exception message for debugging
        echo json_encode(['success' => false, 'message' => 'Failed to stop session: ' . $e->getMessage()]);
        exit;
    }

    // Also mark associated batch as terminated and reset egg count
    $pdo->prepare(
        "UPDATE batches
            SET status = 'terminated', terminated_at = NOW(), egg_count = 0
         WHERE incubator_id = ? AND status = 'incubating'"
    )->execute([$incubator_id]);

    // Also insert an 'all_off' hardware command so ESP will stop relays promptly
    $pdo->prepare(
        "INSERT INTO hardware_commands (incubator_id, command, issued_at)
         VALUES (?, 'all_off', NOW())"
    )->execute([$incubator_id]);

    // Log activity (best-effort; session stop by admin/user)
    $role    = isset($_SESSION['admin_id']) ? 'admin' : 'user';
    $user_id = $role === 'admin' ? ($_SESSION['admin_id'] ?? 0) : ($_SESSION['user_id'] ?? 0);
    logActivity($role, $user_id, 'stop_session', "Session terminated for incubator #{$incubator_id}");

    // Verify if any incubating batches remain for this incubator (debugging aid)
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE incubator_id = ? AND status = 'incubating'");
    $cntStmt->execute([$incubator_id]);
    $remaining = (int)($cntStmt->fetchColumn() ?: 0);

    echo json_encode(['success' => true, 'message' => 'Session terminated', 'remaining_incubating_batches' => $remaining]);
    exit;
}

// ════════════════════════════════════════════
//  8. GET LIVE STATUS  (Dashboard polls every 15 s)
//     Returns the latest values from hardware_state
// ════════════════════════════════════════════
if ($action === 'get_live_status') {
    $isAdmin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
    $isUser = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

    // Verify caller is authenticated (admin or user)
    if (!$isAdmin && !$isUser) {
        json_response(['success' => false, 'message' => 'User not authenticated']);
    }
    
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    if (!$incubator_id) {
        echo json_encode(['success' => false, 'message' => 'Missing incubator_id']);
        exit;
    }
    
    // Verify user has access to this incubator.
    // Admin may access all incubators. User may access via incubator ownership
    // or if they already have batches on that incubator.
    if (!$isAdmin) {
        $userId = (int)$_SESSION['user_id'];
        $userIncubatorCheck = $pdo->prepare(
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
        $userIncubatorCheck->execute([$incubator_id, $userId, $userId]);
        $hasAccess = ((int)$userIncubatorCheck->fetchColumn()) > 0;

        if (!$hasAccess) {
            json_response(['success' => false, 'message' => 'Access denied']);
        }
    }

    $state = fetchLiveState($pdo, $incubator_id);

    if (!$state) {
        json_response([
            'success'     => true,
            'device_status' => 'offline',
            'temperature' => null,
            'current_temp' => null,
            'humidity'    => null,
            'current_humidity' => null,
            'heater_on'   => false,
            'heater_1_status' => false,
            'heater_2_status' => false,
            'heater_fan_status' => false,
            'exhaust_status' => false,
            'swing_on'    => false,
            'swing_status' => false,
            'last_seen'   => null,
            'last_active_at' => null,
            'last_server_sync' => null,
            'last_egg_turn_at' => null,
            'running_ops' => 'idle',
            'wifi_status' => 'disconnected',
            'session_started_at'   => null,
            'session_ends_at'      => null,
            'session_completed_at' => null,
            'session_status'       => 'idle',
            'current_mode'         => 'idle',
            'active_session_name'  => null,
            'incubation_day'       => null,
            'online'      => false
        ]);

        error_log(sprintf("[Hardware API] start_session upsertHardwareState completed for incubator_id=%d", $incubator_id));
    }

    $batchStmt = $pdo->prepare(
        "SELECT batch_name, egg_type, egg_count, start_date, expected_hatch_date
         FROM batches
         WHERE incubator_id = ? AND status = 'incubating'
         ORDER BY created_at DESC LIMIT 1"
    );
    $batchStmt->execute([$incubator_id]);
    $activeBatch = $batchStmt->fetch();

    $incubatingCountStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM batches
         WHERE incubator_id = ? AND status = 'incubating'"
    );
    $incubatingCountStmt->execute([$incubator_id]);
    $incubatingBatchCount = (int)$incubatingCountStmt->fetchColumn();

    // If there are no incubating batches left, do not let stale hardware_state
    // keep the dashboard pinned to RUNNING/INCUBATING after a stop/terminate.
    if (!$activeBatch && $incubatingBatchCount === 0) {
        if (($state['session_status'] ?? 'idle') === 'running' || ($state['current_mode'] ?? 'idle') === 'incubating') {
            $state['session_status'] = !empty($state['session_completed_at']) ? 'completed' : 'idle';
            $state['current_mode'] = 'idle';
            $state['active_session_name'] = null;
            $state['session_ends_at'] = null;
            $pdo->prepare(
                "UPDATE hardware_state
                 SET session_status = ?, current_mode = 'idle', active_session_name = NULL, session_ends_at = NULL, last_server_sync = NOW()
                 WHERE incubator_id = ?"
            )->execute([$state['session_status'], $incubator_id]);
        }
    }

    if ($activeBatch) {
        $needsUpdate = false;
        if (($state['session_status'] ?? 'idle') !== 'running') {
            $state['session_status'] = 'running';
            $state['current_mode'] = 'incubating';
            $needsUpdate = true;
        }
        if (empty($state['active_session_name'])) {
            $state['active_session_name'] = $activeBatch['batch_name'];
            $needsUpdate = true;
        }
        if (empty($state['session_started_at']) && !empty($activeBatch['start_date'])) {
            $state['session_started_at'] = $activeBatch['start_date'] . ' 00:00:00';
            $needsUpdate = true;
        }
        if (empty($state['session_ends_at']) && !empty($activeBatch['expected_hatch_date'])) {
            $state['session_ends_at'] = $activeBatch['expected_hatch_date'] . ' 00:00:00';
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $pdo->prepare(
                "UPDATE hardware_state
                 SET session_status = 'running', current_mode = 'incubating', active_session_name = ?, session_started_at = ?, session_ends_at = ?, last_server_sync = NOW()
                 WHERE incubator_id = ?"
            )->execute([
                $state['active_session_name'],
                $state['session_started_at'],
                $state['session_ends_at'],
                $incubator_id
            ]);
        }
    }

    if ($state['session_status'] === 'running' && !empty($state['session_ends_at']) && strtotime($state['session_ends_at']) <= time()) {
        $pdo->prepare(
            "UPDATE hardware_state
             SET session_status = 'completed', session_completed_at = NOW(), current_mode = 'hatching', last_server_sync = NOW()
             WHERE incubator_id = ?"
        )->execute([$incubator_id]);
        $pdo->prepare(
            "UPDATE batches
                SET status = 'completed', completed_at = NOW()
             WHERE incubator_id = ? AND status = 'incubating'"
        )->execute([$incubator_id]);
        $state['session_status'] = 'completed';
        $state['session_completed_at'] = date('Y-m-d H:i:s');
        $state['current_mode'] = 'hatching';
    }

    $state['current_mode'] = $state['current_mode'] ?: deriveCurrentMode($state);
    $state['temperature'] = $state['display_temp'] ?? $state['temperature'];
    $state['humidity'] = $state['display_humidity'] ?? $state['humidity'];

    $lastSeen = !empty($state['last_seen']) ? strtotime($state['last_seen']) : 0;
    $online   = $lastSeen > 0 && (time() - $lastSeen) < 10;

    $incubationDay = null;
    if (!empty($state['session_started_at'])) {
        $startedAt = strtotime($state['session_started_at']);
        if ($startedAt > 0) {
            $incubationDay = max(1, (int)floor((time() - $startedAt) / 86400) + 1);
        }
    }

    $activeSessionName = $state['active_session_name'] ?? $state['batch_session_name'] ?? null;
    $activeBatchId = $activeBatch ? (int)$activeBatch['id'] : (($state['session_id'] ?? null) ? (int)$state['session_id'] : null);
    $eggType = $activeBatch['egg_type'] ?? null;
    $eggCount = isset($activeBatch['egg_count']) ? (int)$activeBatch['egg_count'] : null;
    $state['heater_on'] = deriveHeaterGroupState($state) ? 1 : 0;
    $runningOps = $state['running_ops'] ?: buildRunningOps($state);
    $lockdown = calculateTurningLockdownState($state);

    $settingsStmt = $pdo->prepare(
        "SELECT id, target_temp, min_temp, max_temp, target_humidity, min_humidity, max_humidity, turning_interval
         FROM temperature_settings
         WHERE incubator_id = ?
         LIMIT 1"
    );
    $settingsStmt->execute([$incubator_id]);
    $settingsRow = $settingsStmt->fetch();

    $defaultSettings = [
        'id' => null,
        'target_temp' => 37.5,
        'min_temp' => 37.0,
        'max_temp' => 38.0,
        'target_humidity' => 55.0,
        'min_humidity' => 50.0,
        'max_humidity' => 60.0,
        'turning_interval' => 8
    ];

    if (!$settingsRow) {
        $settingsRow = $defaultSettings;
    } else {
        foreach ($defaultSettings as $key => $value) {
            if (!isset($settingsRow[$key]) || $settingsRow[$key] === null || $settingsRow[$key] === '') {
                $settingsRow[$key] = $value;
            }
        }
    }

    $tempSettingsId = $settingsRow['id'] ?? null;

    // compute session number (count of batches for this incubator)
    $sessionCountStmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE incubator_id = ?");
    $sessionCountStmt->execute([$incubator_id]);
    $sessionNumber = (int)$sessionCountStmt->fetchColumn();

    json_response([
        'success'     => true,
        'device_status' => $online ? 'online' : 'offline',
        'temperature' => $state['temperature'],
        'current_temp' => $state['temperature'],
        'humidity'    => $state['humidity'],
        'current_humidity' => $state['humidity'],
        'heater_on'   => (bool)$state['heater_on'],
        'heater_1_status' => (bool)($state['heater_1_status'] ?? $state['heater_on']),
        'heater_2_status' => (bool)($state['heater_2_status'] ?? $state['heater_on']),
        'heater_fan_status' => (bool)($state['heater_fan_status'] ?? $state['heater_on']),
        'exhaust_status' => (bool)($state['exhaust_status'] ?? 0),
        'swing_on'    => (bool)$state['swing_on'],
        'swing_status' => (bool)($state['swing_status'] ?? $state['swing_on']),
        'last_seen'   => $state['last_seen'],
        'last_active_at' => $state['last_active_at'] ?? $state['last_seen'],
        'last_server_sync' => $state['last_server_sync'] ?? $state['last_seen'],
        'last_egg_turn_at' => $state['last_egg_turn_at'] ?? null,
        'running_ops' => $runningOps,
        'wifi_status' => $state['wifi_status'] ?? 'disconnected',
        'session_started_at'   => $state['session_started_at'],
        'session_ends_at'      => $state['session_ends_at'],
        'session_completed_at' => $state['session_completed_at'],
        'session_status'       => $state['session_status'],
        'current_mode'         => $state['current_mode'],
        'active_batch_id'      => $activeBatchId,
        'active_session_name'  => $activeSessionName,
        'incubation_day'       => $incubationDay,
        'turning_lockdown_active' => $lockdown['turning_lockdown_active'],
        'turning_lockdown_days_remaining' => $lockdown['turning_lockdown_days_remaining'],
        'temp_settings_id'     => $tempSettingsId ? (int)$tempSettingsId : null,
        // session_number: how many batches/sessions have been created for this incubator (used for UI "pang ilang session")
        'session_number'       => $sessionNumber,
        'egg_type'             => $eggType,
        'egg_count'            => $eggCount,
        'target_temp'          => $settingsRow['target_temp'] ?? null,
        'min_temp'             => $settingsRow['min_temp'] ?? null,
        'max_temp'             => $settingsRow['max_temp'] ?? null,
        'target_humidity'      => $settingsRow['target_humidity'] ?? null,
        'min_humidity'         => $settingsRow['min_humidity'] ?? null,
        'max_humidity'         => $settingsRow['max_humidity'] ?? null,
        'turning_interval'     => $settingsRow['turning_interval'] ?? null,
        'swing_duration_sec'   => $state['swing_duration_sec'] ?? null,
        'online'      => $online
    ]);

    error_log(sprintf("[Hardware API] start_session response -> success for incubator_id=%d, batch_id=%d", $incubator_id, $batchId));
}

// ════════════════════════════════════════════
//  8. SET SWING DURATION
//     Dashboard sends a custom duration before swing_on command
//     Stored in hardware_state so ESP8266 reads it next poll
// ════════════════════════════════════════════
if ($action === 'set_swing_duration') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $duration_sec = (int)($_POST['duration_sec'] ?? 30);

    if (!$incubator_id || $duration_sec < 5 || $duration_sec > 300) {
        echo json_encode(['success' => false, 'message' => 'Invalid duration (5–300 s)']);
        exit;
    }

    // Store duration in hardware_state.swing_duration_sec
    $pdo->prepare(
        "INSERT INTO hardware_state (incubator_id, swing_duration_sec)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE swing_duration_sec = VALUES(swing_duration_sec)"
    )->execute([$incubator_id, $duration_sec]);

    json_response(['success' => true, 'duration_sec' => $duration_sec]);
}

// ════════════════════════════════════════════
//  TEST MODE: Dedicated database tables
//  - test_mode_relay: Pending relay commands
//  - test_mode_dht:   Current temp/humidity
// ════════════════════════════════════════════

// ════════════════════════════════════════════
//  TEST MODE: Get current DHT readings
// ════════════════════════════════════════════
// Called by: test.php (GET /ajax/hardware_api.php?action=test_mode_get_dht&incubator_id=1)
// Returns: Latest temperature & humidity & relay status from ESP
// ════════════════════════════════════════════
if ($action === 'test_mode_get_dht') {
    $incubator_id = (int)($_GET['incubator_id'] ?? $_POST['incubator_id'] ?? 0);
    
    if (!$incubator_id) {
        json_response(['success' => false, 'error' => 'Missing incubator_id']);
    }
    
    try {
        // Ensure tables exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_dht (
            incubator_id INT NOT NULL PRIMARY KEY,
            temp DECIMAL(5,2) DEFAULT NULL,
            humidity DECIMAL(5,2) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay_status (
            incubator_id INT NOT NULL,
            relay VARCHAR(50) NOT NULL,
            state TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (incubator_id, relay)
        )");
        
        $stmt = $pdo->prepare("
            SELECT temp, humidity, wifi_ssid, device_ip, updated_at FROM test_mode_dht 
            WHERE incubator_id = ?
        ");
        $stmt->execute([$incubator_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Get relay status
            $relayStmt = $pdo->prepare("
                SELECT relay, state FROM test_mode_relay_status 
                WHERE incubator_id = ?
            ");
            $relayStmt->execute([$incubator_id]);
            $relayRows = $relayStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $relays = [];
            foreach ($relayRows as $relay) {
                $relays[$relay['relay']] = (bool)$relay['state'];
            }
            
            json_response([
                'success' => true,
                'temp' => $row['temp'] ? (float)$row['temp'] : null,
                'humidity' => $row['humidity'] ? (float)$row['humidity'] : null,
                'wifi_ssid' => $row['wifi_ssid'] ?? null,
                'device_ip' => $row['device_ip'] ?? null,
                'updated_at' => $row['updated_at'],
                'relays' => [
                    'heater_1' => ['state' => (bool)($relays['heater_1'] ?? false), 'pin' => 'D5', 'pin_level' => ($relays['heater_1'] ?? false) ? 'HIGH' : 'LOW'],
                    'heater_2' => ['state' => (bool)($relays['heater_2'] ?? false), 'pin' => 'D6', 'pin_level' => ($relays['heater_2'] ?? false) ? 'HIGH' : 'LOW'],
                    'heater_fan' => ['state' => (bool)($relays['heater_fan'] ?? false), 'pin' => 'D7', 'pin_level' => ($relays['heater_fan'] ?? false) ? 'HIGH' : 'LOW'],
                    'eggswing' => ['state' => (bool)($relays['swing_on'] ?? false), 'pin' => 'D1', 'pin_level' => ($relays['swing_on'] ?? false) ? 'HIGH' : 'LOW'],
                    'exhaust' => ['state' => (bool)($relays['exhaust'] ?? false), 'pin' => 'D0', 'pin_level' => ($relays['exhaust'] ?? false) ? 'HIGH' : 'LOW']
                ]
            ]);
        } else {
            json_response(['success' => false, 'error' => 'No data yet']);
        }
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error']);
    }
}

// ════════════════════════════════════════════
//  TEST MODE: Set DHT readings (from ESP)
// ════════════════════════════════════════════
// Called by: ESP8266 (POST /ajax/hardware_api.php?action=test_mode_set_dht)
// Stores: Current temperature & humidity readings
// ════════════════════════════════════════════
if ($action === 'test_mode_set_dht') {
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $temp = (float)($_POST['temp'] ?? 0);
    $humidity = (float)($_POST['humidity'] ?? 0);
    $wifi_ssid = trim((string)($_POST['wifi_ssid'] ?? ''));
    $device_ip = trim((string)($_POST['device_ip'] ?? ''));
    $wifi_connected = (int)($_POST['wifi_connected'] ?? 0);
    $heater_on = isset($_POST['heater_on']) ? (int)$_POST['heater_on'] : null;
    $heater_fan = isset($_POST['heater_fan_status']) ? (int)$_POST['heater_fan_status'] : (isset($_POST['heater_fan']) ? (int)$_POST['heater_fan'] : null);
    $heater_1 = isset($_POST['heater_1_status']) ? (int)$_POST['heater_1_status'] : (isset($_POST['heater_1']) ? (int)$_POST['heater_1'] : null);
    $heater_2 = isset($_POST['heater_2_status']) ? (int)$_POST['heater_2_status'] : (isset($_POST['heater_2']) ? (int)$_POST['heater_2'] : null);
    $swing_on = isset($_POST['swing_on']) ? (int)$_POST['swing_on'] : (isset($_POST['eggswing']) ? (int)$_POST['eggswing'] : null);
    $exhaust = isset($_POST['exhaust_status']) ? (int)$_POST['exhaust_status'] : (isset($_POST['exhaust']) ? (int)$_POST['exhaust'] : null);
    
    if (!$incubator_id) {
        json_response(['success' => false, 'error' => 'Missing incubator_id']);
    }
    
    try {
        // Ensure table exists with WiFi columns
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_dht (
            incubator_id INT NOT NULL PRIMARY KEY,
            temp DECIMAL(5,2) DEFAULT NULL,
            humidity DECIMAL(5,2) DEFAULT NULL,
            wifi_ssid VARCHAR(100) DEFAULT NULL,
            device_ip VARCHAR(15) DEFAULT NULL,
            wifi_connected TINYINT(1) DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Add columns if they don't exist (for existing tables)
        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM test_mode_dht")->fetchAll() as $col) {
            $columns[$col['Field']] = true;
        }
        if (!isset($columns['wifi_ssid'])) {
            $pdo->exec("ALTER TABLE test_mode_dht ADD COLUMN wifi_ssid VARCHAR(100) DEFAULT NULL");
        }
        if (!isset($columns['device_ip'])) {
            $pdo->exec("ALTER TABLE test_mode_dht ADD COLUMN device_ip VARCHAR(15) DEFAULT NULL");
        }
        if (!isset($columns['wifi_connected'])) {
            $pdo->exec("ALTER TABLE test_mode_dht ADD COLUMN wifi_connected TINYINT(1) DEFAULT 0");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay_status (
            incubator_id INT NOT NULL,
            relay VARCHAR(50) NOT NULL,
            state TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (incubator_id, relay),
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        )");
        
        // Insert or update current readings with WiFi info
        $pdo->prepare("
            INSERT INTO test_mode_dht (incubator_id, temp, humidity, wifi_ssid, device_ip, wifi_connected)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE temp = VALUES(temp), humidity = VALUES(humidity), wifi_ssid = VALUES(wifi_ssid), device_ip = VALUES(device_ip), wifi_connected = VALUES(wifi_connected)
        ")->execute([$incubator_id, $temp, $humidity, $wifi_ssid, $device_ip, $wifi_connected]);

        // Always store relay states (even if 0/OFF)
        $previous = fetchLiveState($pdo, $incubator_id);
        $currentHeaterFan = $heater_fan !== null ? $heater_fan : (int)($previous['heater_fan_status'] ?? 0);
        $currentHeater1 = $heater_1 !== null ? $heater_1 : (int)($previous['heater_1_status'] ?? 0);
        $currentHeater2 = $heater_2 !== null ? $heater_2 : (int)($previous['heater_2_status'] ?? 0);
        $currentSwing = $swing_on !== null ? $swing_on : (int)($previous['swing_on'] ?? 0);
        $currentExhaust = $exhaust !== null ? $exhaust : (int)($previous['exhaust_status'] ?? 0);
        $derivedHeater = ($heater_on !== null)
            ? $heater_on
            : (($currentHeater1 || $currentHeater2) ? 1 : (int)($previous['heater_on'] ?? 0));

        // Update hardware_state for get_health_status display
        $dbNow = (string)$pdo->query("SELECT NOW()")->fetchColumn();
        upsertHardwareState($pdo, $incubator_id, [
            'temperature' => $temp,
            'humidity' => $humidity,
            'device_status' => 'online',
            'current_temp' => $temp,
            'current_humidity' => $humidity,
            'heater_on' => $derivedHeater,
            'heater_1_status' => $currentHeater1,
            'heater_2_status' => $currentHeater2,
            'heater_fan_status' => $currentHeaterFan,
            'exhaust_status' => $currentExhaust,
            'swing_on' => $currentSwing,
            'swing_status' => $currentSwing,
            'last_seen' => $dbNow,
            'last_active_at' => $dbNow,
            'last_server_sync' => $dbNow,
            'wifi_status' => 'connected',
        ]);

        // Store individual relay states in test_mode_relay_status
        $relayStates = [
            'heater' => $derivedHeater,
            'heater_fan' => $currentHeaterFan,
            'heater_1' => $currentHeater1,
            'heater_2' => $currentHeater2,
            'eggswing' => $currentSwing,
            'exhaust' => $currentExhaust,
        ];

        $relayStmt = $pdo->prepare(
            "INSERT INTO test_mode_relay_status (incubator_id, relay, state)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = NOW()"
        );
        foreach ($relayStates as $relay => $relayState) {
            $relayStmt->execute([$incubator_id, $relay, (int)$relayState]);
        }
        
        json_response([
            'success' => true, 
            'message' => 'DHT data stored',
            'received' => [
                'temp' => $temp,
                'humidity' => $humidity,
                'relays' => [
                    'heater_1' => $currentHeater1,
                    'heater_2' => $currentHeater2,
                    'heater_fan' => $currentHeaterFan,
                    'eggswing' => $currentSwing,
                    'exhaust' => $currentExhaust,
                ]
            ]
        ]);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error']);
    }
}

// ════════════════════════════════════════════
//  TEST MODE: Get relay command (from ESP)
// ════════════════════════════════════════════
// Called by: ESP8266 (GET /ajax/hardware_api.php?action=test_mode_get_relay&incubator_id=1)
// Returns: Current relay state (does NOT delete)
// ════════════════════════════════════════════
if ($action === 'test_mode_get_relay') {
    $incubator_id = (int)($_GET['incubator_id'] ?? $_POST['incubator_id'] ?? 0);
    
    if (!$incubator_id) {
        json_response(['success' => false, 'error' => 'Missing incubator_id']);
    }
    
    try {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay (
            incubator_id INT NOT NULL PRIMARY KEY,
            relay VARCHAR(50) NOT NULL,
            state TINYINT(1) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        )");
        
        $stmt = $pdo->prepare("
            SELECT relay, state FROM test_mode_relay 
            WHERE incubator_id = ?
        ");
        $stmt->execute([$incubator_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            json_response([
                'success' => true,
                'relay' => $row['relay'],
                'state' => (bool)$row['state']
            ]);
        } else {
            json_response(['success' => false, 'message' => 'No pending command']);
        }
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error']);
    }
}

// ════════════════════════════════════════════
//  TEST MODE: Get relay statuses for dashboard
// ════════════════════════════════════════════
// Called by: test.php (GET /ajax/hardware_api.php?action=test_mode_get_relay_status&incubator_id=1)
// Returns: Current known state of all relays from test_mode_relay_status table
// ════════════════════════════════════════════
if ($action === 'test_mode_get_relay_status') {
    $incubator_id = (int)($_GET['incubator_id'] ?? $_POST['incubator_id'] ?? 0);

    if (!$incubator_id) {
        json_response(['success' => false, 'error' => 'Missing incubator_id']);
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay_status (
            incubator_id INT NOT NULL,
            relay VARCHAR(50) NOT NULL,
            state TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (incubator_id, relay),
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        )");

        // Read actual test relay state from the shared test_mode_relay_status table
        $stmt = $pdo->prepare(
            "SELECT relay, state
             FROM test_mode_relay_status
             WHERE incubator_id = ?"
        );
        $stmt->execute([$incubator_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $states = [
            'heater' => false,
            'heater_fan' => false,
            'heater_1' => false,
            'heater_2' => false,
            'eggswing' => false,
            'exhaust' => false,
        ];

        foreach ($rows as $row) {
            if (array_key_exists($row['relay'], $states)) {
                $states[$row['relay']] = (bool)$row['state'];
            }
        }

        $states['heater'] = (bool)($states['heater_1'] || $states['heater_2'] || $states['heater']);

        json_response(['success' => true, 'states' => $states]);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    }
}

// ════════════════════════════════════════════
//  TEST MODE: Set relay command (from test.php)
// ════════════════════════════════════════════
// Called by: test.php (POST /ajax/hardware_api.php?action=test_mode_set_relay)
// Stores: Pending relay command for ESP to fetch + updates hardware_state
// ════════════════════════════════════════════
if ($action === 'test_mode_set_relay') {
    // Verify user is authenticated
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'error' => 'User not authenticated']);
    }
    
    $incubator_id = (int)($_POST['incubator_id'] ?? 0);
    $relay = strtolower(trim($_POST['relay'] ?? ''));
    $state = (int)($_POST['state'] ?? 0);
    $sourcePage = strtolower(trim((string)($_POST['source_page'] ?? '')));

    // Allow common aliases from UI/manual calls, then normalize.
    $relayAliases = [
        'heaterfan' => 'heater_fan',
        'fan' => 'heater_fan',
        'h1' => 'heater_1',
        'h2' => 'heater_2',
        'egg_swing' => 'eggswing',
        'swing' => 'eggswing',
        'exhau' => 'exhaust'
    ];
    if (isset($relayAliases[$relay])) {
        $relay = $relayAliases[$relay];
    }

    $allowedRelays = ['heater', 'heater_fan', 'heater_1', 'heater_2', 'eggswing', 'exhaust'];
    
    if (!$incubator_id || !$relay) {
        json_response(['success' => false, 'error' => 'Missing incubator_id or relay']);
    }
    if ($sourcePage !== 'accounts') {
        json_response(['success' => false, 'error' => 'Relay testing is allowed only from /user/accounts.php']);
    }
    if (!in_array($relay, $allowedRelays, true)) {
        json_response(['success' => false, 'error' => 'Unknown relay']);
    }
    
    // Verify user has access to this incubator (must have a batch assigned to it)
    $userIncubatorCheck = $pdo->prepare(
        "SELECT COUNT(*) as cnt FROM batches WHERE incubator_id = ? AND user_id = ?"
    );
    $userIncubatorCheck->execute([$incubator_id, $_SESSION['user_id']]);
    $hasAccess = $userIncubatorCheck->fetchColumn() > 0;
    
    if (!$hasAccess) {
        json_response(['success' => false, 'error' => 'Access denied: You do not have permission to control this incubator']);
    }
    
    try {
        ensureHardwareStateSchema($pdo);
        
        // Ensure table exists for pending commands
        $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay (
            incubator_id INT NOT NULL PRIMARY KEY,
            relay VARCHAR(50) NOT NULL,
            state TINYINT(1) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        )");
        
        // Store pending command (overwrite if exists)
        $pdo->prepare("
            INSERT INTO test_mode_relay (incubator_id, relay, state)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE relay = VALUES(relay), state = VALUES(state), created_at = NOW()
        ")->execute([$incubator_id, $relay, $state]);

        // Also update hardware_state table so read operation shows the commanded state
        // Map relay name to hardware_state columns
        $updateFields = [];
        switch ($relay) {
            case 'heater':
                $updateFields = ['heater_on' => $state, 'heater_1_status' => $state, 'heater_2_status' => $state];
                break;
            case 'heater_fan':
                $updateFields = ['heater_fan_status' => $state];
                break;
            case 'heater_1':
                $updateFields = ['heater_1_status' => $state];
                break;
            case 'heater_2':
                $updateFields = ['heater_2_status' => $state];
                break;
            case 'eggswing':
                $updateFields = ['swing_status' => $state, 'swing_on' => $state];
                break;
            case 'exhaust':
                $updateFields = ['exhaust_status' => $state];
                break;
        }

        if (!empty($updateFields)) {
            $setClauses = array_map(fn($k) => "$k = ?", array_keys($updateFields));
            $setSQL = implode(', ', $setClauses);
            $values = array_values($updateFields);
            $values[] = $incubator_id;
            
            $sql = "UPDATE hardware_state SET $setSQL, updated_at = NOW() WHERE incubator_id = ?";
            $pdo->prepare($sql)->execute($values);

            $pdo->exec("CREATE TABLE IF NOT EXISTS test_mode_relay_status (
                incubator_id INT NOT NULL,
                relay VARCHAR(50) NOT NULL,
                state TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (incubator_id, relay),
                FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
            )");

            $statusRelayStates = [];
            switch ($relay) {
                case 'heater':
                    $statusRelayStates = [
                        'heater' => $state,
                        'heater_fan' => $state,
                        'heater_1' => $state,
                        'heater_2' => $state,
                    ];
                    break;
                case 'heater_fan':
                    $statusRelayStates = ['heater_fan' => $state];
                    break;
                case 'heater_1':
                    $statusRelayStates = ['heater_1' => $state];
                    break;
                case 'heater_2':
                    $statusRelayStates = ['heater_2' => $state];
                    break;
                case 'eggswing':
                    $statusRelayStates = ['eggswing' => $state];
                    break;
                case 'exhaust':
                    $statusRelayStates = ['exhaust' => $state];
                    break;
            }

            $statusStmt = $pdo->prepare(
                "INSERT INTO test_mode_relay_status (incubator_id, relay, state)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE state = VALUES(state), updated_at = NOW()"
            );
            foreach ($statusRelayStates as $statusRelay => $statusState) {
                $statusStmt->execute([$incubator_id, $statusRelay, $statusState]);
            }
        }
        
        json_response(['success' => true, 'message' => "Command queued: $relay = " . ($state ? 'ON' : 'OFF')]);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    }
}

// ════════════════════════════════════════════
//  GET HEALTH STATUS (Device Online Indicator)
// ════════════════════════════════════════════
// Called by: test.php (GET /ajax/hardware_api.php?action=get_health_status&incubator_id=1)
// Returns: Device online status, WiFi info, relay states, server endpoints
// ════════════════════════════════════════════
if ($action === 'get_health_status') {
    $incubator_id = (int)($_GET['incubator_id'] ?? $_POST['incubator_id'] ?? 0);
    
    if (!$incubator_id) {
        json_response(['success' => false, 'error' => 'Missing incubator_id']);
    }
    
    try {
        ensureHardwareStateSchema($pdo);
        
        $stmt = $pdo->prepare(
            "SELECT hs.*, 
                    TIMESTAMPDIFF(SECOND, hs.last_seen, NOW()) AS seconds_since_last_seen,
                    CASE 
                        WHEN TIMESTAMPDIFF(SECOND, hs.last_seen, NOW()) < 45 THEN 'online'
                        WHEN TIMESTAMPDIFF(SECOND, hs.last_seen, NOW()) < 60 THEN 'idle'
                        ELSE 'offline'
                    END AS status_indicator
             FROM hardware_state hs
             WHERE hs.incubator_id = ?
             LIMIT 1"
        );
        $stmt->execute([$incubator_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ensure we always fetch latest relay states from test_mode_relay_status
        $relayStmt = $pdo->prepare(
            "SELECT relay, state FROM test_mode_relay_status WHERE incubator_id = ?"
        );
        $relayStmt->execute([$incubator_id]);
        $relayRows = $relayStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build relay state map (same format as test_mode_get_dht)
        $relays = [];
        foreach ($relayRows as $relayRow) {
            $relays[$relayRow['relay']] = (bool)$relayRow['state'];
        }

        // If hardware_state is empty or missing wifi/IP/updated_at, try to get data from test_mode_dht (fallback)
        if (empty($state) || empty($state['wifi_ssid']) || empty($state['device_ip']) || empty($state['updated_at'])) {
            error_log("[DEBUG] hardware_state missing fields or empty, trying test_mode_dht fallback");

            $dhtStmt = $pdo->prepare(
                "SELECT temp, humidity, wifi_ssid, device_ip, wifi_connected, updated_at,
                        TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS seconds_since_last_seen
                 FROM test_mode_dht WHERE incubator_id = ?"
            );
            $dhtStmt->execute([$incubator_id]);
            $dhtRow = $dhtStmt->fetch(PDO::FETCH_ASSOC);
            error_log("[DEBUG] dhtRow: " . json_encode($dhtRow));

            if (!empty($dhtRow)) {
                $secondsSince = (int)($dhtRow['seconds_since_last_seen'] ?? 999);

                // If we didn't have a hardware_state row, build a minimal one
                if (empty($state)) {
                    $state = [
                        'incubator_id' => $incubator_id,
                        'temp' => (float)($dhtRow['temp'] ?? 0),
                        'humidity' => (float)($dhtRow['humidity'] ?? 0),
                    ];
                }

                // Prefer hardware_state values but override missing wifi/IP/updated_at with test_mode_dht
                $state['wifi_ssid'] = $state['wifi_ssid'] ?? ($dhtRow['wifi_ssid'] ?? null);
                $state['device_ip'] = $state['device_ip'] ?? ($dhtRow['device_ip'] ?? null);
                $state['wifi_connected'] = $state['wifi_connected'] ?? (int)($dhtRow['wifi_connected'] ?? 0);
                $state['updated_at'] = $state['updated_at'] ?? $dhtRow['updated_at'];
                $state['seconds_since_last_seen'] = $state['seconds_since_last_seen'] ?? $secondsSince;
                $state['status_indicator'] = $state['status_indicator'] ?? ($secondsSince < 10 ? 'online' : ($secondsSince < 20 ? 'idle' : 'offline'));

                error_log("[DEBUG] Merged state after test_mode_dht fallback: " . json_encode($state));
            }
        }

        error_log("[DEBUG] Final state before health response: " . json_encode($state));
        error_log("[DEBUG] Final relays before health response: " . json_encode($relays));

        // Build relays response with pin info
        $relayResponse = [
            'heater_1' => [
                'state' => isset($relays['heater_1']) ? $relays['heater_1'] : false,
                'pin' => 'D5'
            ],
            'heater_2' => [
                'state' => isset($relays['heater_2']) ? $relays['heater_2'] : false,
                'pin' => 'D6'
            ],
            'heater_fan' => [
                'state' => isset($relays['heater_fan']) ? $relays['heater_fan'] : false,
                'pin' => 'D7'
            ],
            'eggswing' => [
                'state' => isset($relays['eggswing']) ? $relays['eggswing'] : false,
                'pin' => 'D1'
            ],
            'exhaust' => [
                'state' => isset($relays['exhaust']) ? $relays['exhaust'] : false,
                'pin' => 'D0'
            ],
        ];

        $health = [
            'success' => true,
            'device_id' => $incubator_id,
            'status_indicator' => !empty($state) ? ($state['status_indicator'] ?? 'offline') : 'offline',
            'last_seen' => !empty($state) ? ($state['updated_at'] ?? null) : null,
            'seconds_since_last_seen' => !empty($state) ? ($state['seconds_since_last_seen'] ?? null) : null,
            'wifi_connected' => !empty($state) ? (bool)($state['wifi_connected'] ?? 0) : false,
            'wifi_ssid' => !empty($state) ? ($state['wifi_ssid'] ?? null) : null,
            'device_ip' => !empty($state) ? ($state['device_ip'] ?? null) : null,
            'current_temp' => !empty($state) ? (float)($state['temp'] ?? 0) : 0,
            'current_humidity' => !empty($state) ? (float)($state['humidity'] ?? 0) : 0,
            'relays' => $relayResponse,
            'endpoints' => [
                'health' => '/ajax/hardware_api.php?action=get_health_status&incubator_id=' . $incubator_id,
                'dht' => '/ajax/hardware_api.php?action=test_mode_get_dht&incubator_id=' . $incubator_id,
            ]
        ];
        
        json_response($health);
    } catch (Exception $e) {
        json_response(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    }
}

// ════════════════════════════════════════════
//  Fallthrough
// ════════════════════════════════════════════
json_response(['success' => false, 'message' => 'Unknown action']);