<?php
// GHOST Incubator System - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ghost_incubator');
define('SITE_NAME', 'GHOST Incubator');
define('SITE_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/GHOST');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PDO Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                )
            );
        } catch (PDOException $e) {
            die(json_encode(array('success' => false, 'message' => 'Database connection failed: ' . $e->getMessage())));
        }
    }
    return $pdo;
}

// Auth helpers
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit();
    }
}

function requireUser() {
    if (!isUserLoggedIn()) {
        header('Location: ' . BASE_URL . '/user/login.php');
        exit();
    }
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) return null;
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute(array($_SESSION['admin_id']));
    return $stmt->fetch();
}

function getCurrentUser() {
    if (!isUserLoggedIn()) return null;
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch();
}

function logActivity($role, $userId, $action, $details = '') {
    try {
        $pdo  = getDB();
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute(array($role, $userId, $action, $details, $ip));
    } catch (Exception $e) {}
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// PHP 7 compatible helpers replacing PHP 8 match() expressions
function actionIcon($type, $withLabel = false) {
    $map = array(
        'turning'           => array('&#x1F504;', 'Turning'),
        'temperature_check' => array('&#x1F321;&#xFE0F;', 'Temp Check'),
        'humidity_check'    => array('&#x1F4A7;', 'Humidity'),
        'candling'          => array('&#x1F56F;&#xFE0F;', 'Candling'),
        'hatch'             => array('&#x1F423;', 'Hatch'),
        'maintenance'       => array('&#x2699;&#xFE0F;', 'Maintenance'),
    );
    $icon  = isset($map[$type]) ? $map[$type][0] : '&#x2699;&#xFE0F;';
    $label = isset($map[$type]) ? $map[$type][1] : htmlspecialchars($type);
    return $withLabel ? $icon . ' ' . $label : $icon;
}

function severityIcon($severity) {
    if ($severity === 'warning') return '&#x26A0;&#xFE0F;';
    if ($severity === 'danger')  return '&#x1F6A8;';
    return '&#x2139;&#xFE0F;';
}

// Automatic batch scheduler
function runBatchScheduler() {
    try {
        $pdo = getDB();
        // Run in transaction to avoid race conditions
        $pdo->beginTransaction();

        $now = date('Y-m-d H:i:s');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

        // 1) Process pending schedules whose scheduled time has arrived
        $pendingSchedulesStmt = $pdo->prepare(
            "SELECT s.id, s.incubator_id, s.batch_id, s.title, s.description, s.scheduled_date, s.scheduled_time, s.created_by_id, s.created_by_role
             FROM schedules s
             WHERE s.status = 'pending' 
             AND CONCAT(s.scheduled_date, ' ', s.scheduled_time) <= ?
             ORDER BY s.scheduled_date ASC, s.scheduled_time ASC"
        );
        $pendingSchedulesStmt->execute([$now]);
        $pendingSchedules = $pendingSchedulesStmt->fetchAll();
        $schedulesProcessed = 0;

        $orphanSchedulesStmt = $pdo->prepare(
            "SELECT s.id, s.incubator_id, s.title, s.description, s.scheduled_date, s.scheduled_time, s.created_by_id
             FROM schedules s
             WHERE s.status = 'pending' AND s.batch_id IS NULL"
        );
        $orphanSchedulesStmt->execute();
        $orphanSchedules = $orphanSchedulesStmt->fetchAll();

        foreach ($orphanSchedules as $sched) {
            $desc = [];
            if (!empty($sched['description'])) {
                $desc = json_decode($sched['description'], true) ?: [];
            }

            $sessionDuration = isset($desc['session_duration_sec']) ? (int)$desc['session_duration_sec'] : 0;
            if ($sessionDuration <= 0) {
                continue;
            }

            $scheduledDateTime = trim($sched['scheduled_date'] . ' ' . ($sched['scheduled_time'] ?? '00:00'));
            $startTs = strtotime($scheduledDateTime) ?: time();
            $batchStartDate = date('Y-m-d', $startTs);
            $batchHatchDate = date('Y-m-d', strtotime('+' . $sessionDuration . ' seconds', $startTs));
            $eggCount = isset($desc['egg_count']) ? (int)$desc['egg_count'] : 0;
            $eggType = isset($desc['egg_type']) ? trim((string)$desc['egg_type']) : 'Chicken';
            $batchName = sprintf('%s - %d eggs', ($sched['title'] ?: 'Batch'), $eggCount);

            $ins = $pdo->prepare(
                "INSERT INTO batches (incubator_id, user_id, batch_name, egg_type, egg_count, start_date, expected_hatch_date, status, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())"
            );
            $ins->execute([
                $sched['incubator_id'],
                (int)($sched['created_by_id'] ?: 0),
                $batchName,
                $eggType,
                $eggCount,
                $batchStartDate,
                $batchHatchDate,
                'SCHEDULED_START: ' . date('Y-m-d H:i:s', $startTs)
            ]);

            $batchId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE schedules SET batch_id = ? WHERE id = ?")->execute([$batchId, $sched['id']]);
        }

        foreach ($pendingSchedules as $sched) {
            $schedId = $sched['id'];
            $incubatorId = $sched['incubator_id'];
            $batchId = $sched['batch_id'];
            $schedTitle = $sched['title'];

            try {
                // Check if incubator already has a running session
                $hsStmt = $pdo->prepare("SELECT session_status FROM hardware_state WHERE incubator_id = ? LIMIT 1");
                $hsStmt->execute([$incubatorId]);
                $hs = $hsStmt->fetch();
                if ($hs && ($hs['session_status'] ?? '') === 'running') {
                    throw new Exception("Incubator already has a running session");
                }

                // If schedule has no batch_id yet, create the backing batch now so the dashboard can track it.
                if (!$batchId) {
                    $desc = [];
                    if (!empty($sched['description'])) {
                        $desc = json_decode($sched['description'], true) ?: [];
                    }

                    $sessionDuration = isset($desc['session_duration_sec']) ? (int)$desc['session_duration_sec'] : 0;
                    if ($sessionDuration > 0) {
                        $eggCount = isset($desc['egg_count']) ? (int)$desc['egg_count'] : 0;
                        $eggType = isset($desc['egg_type']) ? trim((string)$desc['egg_type']) : 'Chicken';
                        $scheduledDateTime = trim($sched['scheduled_date'] . ' ' . ($sched['scheduled_time'] ?? '00:00'));
                        $startTs = strtotime($scheduledDateTime) ?: time();
                        $batchStartDate = date('Y-m-d', $startTs);
                        $batchHatchDate = date('Y-m-d', strtotime('+' . $sessionDuration . ' seconds', $startTs));
                        $batchName = sprintf('%s - %d eggs', ($sched['title'] ?: 'Batch'), $eggCount);

                        $ins = $pdo->prepare(
                            "INSERT INTO batches (incubator_id, user_id, batch_name, egg_type, egg_count, start_date, expected_hatch_date, status, notes, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())"
                        );
                        $ins->execute([
                            $incubatorId,
                            (int)($sched['created_by_id'] ?: 0),
                            $batchName,
                            $eggType,
                            $eggCount,
                            $batchStartDate,
                            $batchHatchDate,
                            'SCHEDULED_START: ' . date('Y-m-d H:i:s', $startTs)
                        ]);
                        $batchId = (int)$pdo->lastInsertId();
                        $pdo->prepare("UPDATE schedules SET batch_id = ? WHERE id = ?")->execute([$batchId, $schedId]);
                    }
                }

                // If schedule has a batch_id, use it; otherwise just mark as done
                if ($batchId) {
                    $batchStmt = $pdo->prepare("SELECT id, incubator_id, batch_name, start_date, expected_hatch_date FROM batches WHERE id = ?");
                    $batchStmt->execute([$batchId]);
                    $batch = $batchStmt->fetch();

                    if (!$batch) {
                        throw new Exception("Batch {$batchId} not found");
                    }

                    // Mark batch as incubating
                    $pdo->prepare("UPDATE batches SET status = 'incubating' WHERE id = ?")->execute([$batchId]);

                    // Upsert hardware_state for this incubator
                    $endsAt = !empty($batch['expected_hatch_date']) ? ($batch['expected_hatch_date'] . ' 00:00:00') : null;
                    $upsert = $pdo->prepare(
                        "INSERT INTO hardware_state (incubator_id, session_status, session_started_at, session_ends_at, current_mode, active_session_name, last_server_sync, last_seen, last_active_at)
                         VALUES (?, 'running', ?, ?, 'incubating', ?, NOW(), NOW(), NOW())
                         ON DUPLICATE KEY UPDATE session_status = 'running', session_started_at = VALUES(session_started_at), session_ends_at = VALUES(session_ends_at), current_mode = 'incubating', active_session_name = VALUES(active_session_name), last_server_sync = NOW(), last_seen = NOW(), last_active_at = NOW()"
                    );
                    $upsert->execute([$incubatorId, $now, $endsAt, $batch['batch_name']]);

                    // Mark schedule as done
                    $pdo->prepare("UPDATE schedules SET status = 'done' WHERE id = ?")->execute([$schedId]);

                    $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)")
                        ->execute(['system', null, 'auto_schedule_started', "Schedule '{$schedTitle}' (ID {$schedId}) started batch {$batchId} for incubator {$incubatorId}", $ip]);
                } else {
                    // Schedule with no batch - just mark as done
                    $pdo->prepare("UPDATE schedules SET status = 'done' WHERE id = ?")->execute([$schedId]);
                    $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)")
                        ->execute(['system', null, 'auto_schedule_processed', "Schedule '{$schedTitle}' (ID {$schedId}) processed (no batch assigned)", $ip]);
                }
                $schedulesProcessed++;

            } catch (Exception $scheduleEx) {
                // Mark schedule and associated batch as failed
                $pdo->prepare("UPDATE schedules SET status = 'failed' WHERE id = ?")->execute([$schedId]);
                if ($batchId) {
                    $pdo->prepare("UPDATE batches SET status = 'failed' WHERE id = ?")->execute([$batchId]);
                }
                $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)")
                    ->execute(['system', null, 'auto_schedule_failed', "Schedule '{$schedTitle}' (ID {$schedId}) failed: {$scheduleEx->getMessage()}", $ip]);
            }
        }

        // 2) End incubating batches whose expected_hatch_date has arrived (or passed)
        $toEndStmt = $pdo->prepare("SELECT id, incubator_id FROM batches WHERE status = 'incubating' AND expected_hatch_date <= CURDATE()");
        $toEndStmt->execute();
        $toEnd = $toEndStmt->fetchAll();
        $ended = 0;
        foreach ($toEnd as $b) {
            // Mark batch completed
            $pdo->prepare("UPDATE batches SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$b['id']]);

            // Update hardware_state to completed
            $pdo->prepare(
                "UPDATE hardware_state SET session_status = 'completed', session_completed_at = NOW(), current_mode = 'hatching', last_server_sync = NOW() WHERE incubator_id = ?"
            )->execute([$b['incubator_id']]);

            $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)")
                ->execute(['system', null, 'auto_complete_batch', "Completed batch {$b['id']} for incubator {$b['incubator_id']}", $ip]);
            $ended++;
        }

        $pdo->commit();
        return array('schedules_processed'=>$schedulesProcessed, 'batches_ended'=>$ended);
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        return array('error'=>$e->getMessage());
    }
}

// Run scheduler on each request, but avoid running during CLI/cron tasks
if (php_sapi_name() !== 'cli') {
    // suppress any errors; this is a lightweight background action
    try { runBatchScheduler(); } catch (Exception $ex) {}
}
