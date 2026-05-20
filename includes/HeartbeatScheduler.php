<?php
/**
 * ============================================================
 *  GHOST Incubator — Heartbeat Scheduler
 *  File: /GHOST/includes/HeartbeatScheduler.php
 *
 *  Core state machine for device heartbeat processing:
 *  - Auto-start due scheduled batches
 *  - Manage running sessions
 *  - Apply cooldown logic
 *  - Enforce safety limits
 *  - Auto-stop on duration/safety limits
 *
 *  Called by:
 *    - /ajax/heartbeat.php (main endpoint)
 *    - hardware_api.php (device commands)
 * ============================================================
 */

class HeartbeatScheduler {
    private $pdo;
    private $incubator_id;
    private $current_temp;
    private $current_humidity;
    private $logger;

    // Configuration
    private const COOLDOWN_DURATION_MINUTES = 5;
    private const OVERHEAT_THRESHOLD = 2;         // degrees above target
    private const CRITICAL_OVERHEAT = 3;          // degrees above target
    private const EMERGENCY_SHUTDOWN = 5;         // degrees above target
    private const UNDERCOOL_THRESHOLD = 1;        // degrees below target
    private const MAX_DEVICE_OFFLINE_MINUTES = 60;
    private const SCHEDULE_FAILURE_LOG_PATH = __DIR__ . '/../logs/schedule_start_failures.log';

    public function __construct(PDO $pdo, int $incubator_id, ?float $current_temp = null, ?float $current_humidity = null) {
        $this->pdo = $pdo;
        $this->incubator_id = $incubator_id;
        $this->current_temp = $current_temp;
        $this->current_humidity = $current_humidity;
        $this->logger = new SessionLogger($pdo);
    }

    /**
     * Main heartbeat dispatcher - handles all scheduler logic
     * Returns: ['status' => 'RUN|STOP|COOLDOWN', 'command' => {...}, 'session_id' => int|null]
     */
    public function handleHeartbeat(): array {
        try {
            // Ensure all tables exist
            $this->ensureSchema();

            // Lock on control state to prevent concurrent heartbeats
            $control = $this->acquireControlsLock();
            if (!$control) {
                $control = $this->initializeControlsState();
            }

            // Update device last_seen
            $this->updateDeviceLastSeen();

            // Check for running session
            $session = $this->getRunningSession();

            if ($session) {
                // Handle existing running session
                return $this->updateRunningSession($session, $control);
            }

            // No session running - check for due schedule
            $schedule = $this->findDueSchedule();

            if ($schedule) {
                // Start the due schedule
                try {
                    return $this->startDueSchedule($schedule, $control);
                } catch (Exception $scheduleEx) {
                    $this->logFailedScheduleStart($schedule, $scheduleEx->getMessage());
                    return [
                        'success' => false,
                        'status' => 'STOP',
                        'reason' => 'schedule_start_failed',
                        'schedule_id' => (int)($schedule['id'] ?? 0),
                        'message' => 'Due schedule failed to start',
                        'error' => $scheduleEx->getMessage()
                    ];
                }
            }

            // No schedule - return IDLE/STOP
            return $this->returnIdle();

        } catch (Exception $e) {
            error_log("[HeartbeatScheduler] Error: " . $e->getMessage());
            return [
                'success' => false,
                'status' => 'STOP',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create all necessary tables if they don't exist
     */
    private function ensureSchema(): void {
        // Sessions table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                incubator_id INT NOT NULL,
                batch_id INT DEFAULT NULL,
                schedule_id INT DEFAULT NULL,
                status ENUM('running', 'completed', 'interrupted', 'stopped') NOT NULL DEFAULT 'running',
                target_temp DECIMAL(5,2) NOT NULL,
                target_humidity DECIMAL(5,2) DEFAULT NULL,
                duration_hours DECIMAL(5,2) DEFAULT NULL,
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL,
                reason_ended VARCHAR(255) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                auto_started TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (incubator_id),
                INDEX (batch_id),
                INDEX (schedule_id),
                INDEX (status),
                FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Controls state table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS controls_state (
                id INT NOT NULL PRIMARY KEY DEFAULT 1,
                incubator_id INT UNIQUE NOT NULL,
                session_id INT DEFAULT NULL,
                status ENUM('idle', 'running', 'cooldown', 'stopped', 'error') NOT NULL DEFAULT 'idle',
                target_temp DECIMAL(5,2) DEFAULT NULL,
                target_humidity DECIMAL(5,2) DEFAULT NULL,
                cooldown_until TIMESTAMP NULL,
                safety_triggered ENUM('none', 'overheat', 'critical', 'emergency') NOT NULL DEFAULT 'none',
                relay_heater TINYINT(1) NOT NULL DEFAULT 0,
                relay_swing TINYINT(1) NOT NULL DEFAULT 0,
                relay_exhaust TINYINT(1) NOT NULL DEFAULT 0,
                relay_fan TINYINT(1) NOT NULL DEFAULT 0,
                last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Session event logs
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS session_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                incubator_id INT NOT NULL,
                event_type ENUM('session_start', 'session_end', 'cooldown_enter', 'cooldown_exit', 
                                'safety_alert', 'duration_expired', 'manual_stop', 'device_offline') NOT NULL,
                temperature DECIMAL(5,2) DEFAULT NULL,
                humidity DECIMAL(5,2) DEFAULT NULL,
                message TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (session_id),
                INDEX (incubator_id),
                INDEX (event_type),
                FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Alter schedules table if needed
        $this->ensureScheduleColumns();
    }

    /**
     * Ensure schedules table has required columns
     */
    private function ensureScheduleColumns(): void {
        $columns = [];
        foreach ($this->pdo->query("SHOW COLUMNS FROM schedules")->fetchAll() as $col) {
            $columns[$col['Field']] = true;
        }

        if (!isset($columns['duration_hours'])) {
            $this->pdo->exec("ALTER TABLE schedules ADD COLUMN duration_hours DECIMAL(5,2) DEFAULT NULL");
        }
        if (!isset($columns['auto_start'])) {
            $this->pdo->exec("ALTER TABLE schedules ADD COLUMN auto_start TINYINT(1) DEFAULT 0");
        }
        if (!isset($columns['run_now'])) {
            $this->pdo->exec("ALTER TABLE schedules ADD COLUMN run_now TINYINT(1) DEFAULT 0");
        }
    }

    /**
     * Acquire lock on controls state (SELECT FOR UPDATE)
     */
    private function acquireControlsLock(): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM controls_state 
             WHERE incubator_id = ? 
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$this->incubator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Initialize controls state if it doesn't exist
     */
    private function initializeControlsState(): array {
        $stmt = $this->pdo->prepare(
            "INSERT INTO controls_state (incubator_id, status) 
             VALUES (?, 'idle')
             ON DUPLICATE KEY UPDATE id = id"
        );
        $stmt->execute([$this->incubator_id]);

        $stmt = $this->pdo->prepare("SELECT * FROM controls_state WHERE incubator_id = ?");
        $stmt->execute([$this->incubator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update device last_seen timestamp
     */
    private function updateDeviceLastSeen(): void {
        $stmt = $this->pdo->prepare(
            "UPDATE hardware_state 
             SET last_seen = NOW(), device_status = 'online'
             WHERE incubator_id = ?"
        );
        $stmt->execute([$this->incubator_id]);
    }

    /**
     * Get running session for this incubator
     */
    private function getRunningSession(): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sessions 
             WHERE incubator_id = ? AND status = 'running'
             ORDER BY started_at DESC LIMIT 1"
        );
        $stmt->execute([$this->incubator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find earliest due schedule for this incubator
     * Criteria: status = 'pending' and scheduled_datetime <= now
     */
    private function findDueSchedule(): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM schedules s
             WHERE s.incubator_id = ?
               AND s.status = 'pending'
               AND CONCAT(s.scheduled_date, ' ', s.scheduled_time) <= NOW()
             ORDER BY s.scheduled_date ASC, s.scheduled_time ASC
             LIMIT 1"
        );
        $stmt->execute([$this->incubator_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Handle running session update - check duration, safety, cooldown
     */
    private function updateRunningSession(array $session, array $control): array {
        // Check safety limits
        $safety = $this->checkSafetyLimits($control, $session);
        if ($safety['triggered']) {
            return $this->handleSafetyTriggered($session, $control, $safety);
        }

        // Check if duration expired
        if ($this->isDurationExpired($session)) {
            return $this->autoStopSession($session, 'duration_expired', 'Session duration limit reached');
        }

        // Apply cooldown logic
        $cooldownResponse = $this->applyCooldownLogic($session, $control);
        if ($cooldownResponse) {
            return $cooldownResponse;
        }

        // Session running normally
        return $this->returnRunning($session, $control);
    }

    /**
     * Check safety thresholds (overheat, critical, emergency)
     */
    private function checkSafetyLimits(array $control, array $session): array {
        $result = [
            'triggered' => false,
            'level' => 'none',
            'temp' => $this->current_temp,
            'target' => $control['target_temp'] ?? $session['target_temp']
        ];

        if ($this->current_temp === null || $control['target_temp'] === null) {
            return $result;
        }

        $target = (float)$control['target_temp'];
        $current = (float)$this->current_temp;
        $overheat = $current - $target;

        // Emergency shutdown (>= +5°C above target)
        if ($overheat >= self::EMERGENCY_SHUTDOWN) {
            $result['triggered'] = true;
            $result['level'] = 'emergency';
        }
        // Critical overheat (>= +3°C above target)
        elseif ($overheat >= self::CRITICAL_OVERHEAT) {
            $result['triggered'] = true;
            $result['level'] = 'critical';
        }
        // Warning overheat (>= +2°C above target)
        elseif ($overheat >= self::OVERHEAT_THRESHOLD) {
            $result['triggered'] = true;
            $result['level'] = 'overheat';
        }

        return $result;
    }

    /**
     * Handle safety trigger - stop session and return STOP
     */
    private function handleSafetyTriggered(array $session, array $control, array $safety): array {
        $message = sprintf(
            "Safety limit triggered: %s (Current: %.1f°C, Target: %.1f°C, Δ: +%.1f°C)",
            $safety['level'],
            $safety['temp'],
            $safety['target'],
            ($safety['temp'] - $safety['target'])
        );

        // Update controls to stopped
        $stmt = $this->pdo->prepare(
            "UPDATE controls_state 
             SET status = 'stopped', safety_triggered = ?, relay_heater = 0, relay_swing = 0
             WHERE incubator_id = ?"
        );
        $stmt->execute([$safety['level'], $this->incubator_id]);

        // Update session to interrupted
        $stmt = $this->pdo->prepare(
            "UPDATE sessions 
             SET status = 'interrupted', ended_at = NOW(), reason_ended = ?
             WHERE id = ?"
        );
        $stmt->execute([$message, $session['id']]);

        // Log the event
        $this->logger->logSessionEvent(
            $session['id'],
            $this->incubator_id,
            'safety_alert',
            $this->current_temp,
            $this->current_humidity,
            $message
        );

        // Create activity log
        $activity = sprintf(
            "SAFETY ALERT: %s for incubator #%d - %s",
            strtoupper($safety['level']),
            $this->incubator_id,
            $message
        );
        $this->logger->logActivity('auto', null, 'safety_alert', $activity);

        return [
            'success' => true,
            'status' => 'STOP',
            'reason' => 'safety',
            'safety_level' => $safety['level'],
            'message' => $message,
            'session_id' => $session['id']
        ];
    }

    /**
     * Check if session duration has expired
     */
    private function isDurationExpired(array $session): bool {
        if (!$session['duration_hours'] || !$session['started_at']) {
            return false;
        }

        $startTime = new DateTime($session['started_at']);
        $durationSeconds = (float)$session['duration_hours'] * 3600;
        $endTime = $startTime->modify("+{$durationSeconds} seconds");

        return new DateTime() > $endTime;
    }

    /**
     * Auto-stop session due to duration or safety
     */
    private function autoStopSession(array $session, string $reason, string $message): array {
        // Update session
        $stmt = $this->pdo->prepare(
            "UPDATE sessions 
             SET status = 'completed', ended_at = NOW(), reason_ended = ?
             WHERE id = ?"
        );
        $stmt->execute([$reason, $session['id']]);

        // Update controls to idle
        $stmt = $this->pdo->prepare(
            "UPDATE controls_state 
             SET status = 'idle', session_id = NULL, relay_heater = 0, relay_swing = 0, relay_exhaust = 0
             WHERE incubator_id = ?"
        );
        $stmt->execute([$this->incubator_id]);

        // Mark schedule as done
        if ($session['schedule_id']) {
            $stmt = $this->pdo->prepare(
                "UPDATE schedules SET status = 'done' WHERE id = ?"
            );
            $stmt->execute([$session['schedule_id']]);
        }

        // Log event
        $this->logger->logSessionEvent(
            $session['id'],
            $this->incubator_id,
            $reason === 'duration_expired' ? 'duration_expired' : 'session_end',
            $this->current_temp,
            $this->current_humidity,
            $message
        );

        return [
            'success' => true,
            'status' => 'STOP',
            'reason' => $reason,
            'message' => $message,
            'session_id' => $session['id']
        ];
    }

    /**
     * Apply cooldown logic when target temperature is reached
     */
    private function applyCooldownLogic(array $session, array $control): ?array {
        $target = (float)($control['target_temp'] ?? $session['target_temp']);

        if ($this->current_temp === null) {
            return null;
        }

        $current = (float)$this->current_temp;

        // Not in cooldown but target reached - enter cooldown
        if ($control['status'] !== 'cooldown' && $current >= $target - 0.5) {
            return $this->enterCooldown($session, $control);
        }

        // In cooldown
        if ($control['status'] === 'cooldown') {
            // Check if cooldown should end
            if ($this->shouldExitCooldown($control, $target, $current)) {
                return $this->exitCooldown($session, $control);
            }

            // Still in cooldown
            return $this->returnCooldown($session, $control);
        }

        return null;
    }

    /**
     * Enter cooldown state
     */
    private function enterCooldown(array $session, array $control): array {
        $cooldownUntil = (new DateTime())->modify('+' . self::COOLDOWN_DURATION_MINUTES . ' minutes');

        $stmt = $this->pdo->prepare(
            "UPDATE controls_state 
             SET status = 'cooldown', cooldown_until = ?, relay_heater = 0, relay_swing = 0
             WHERE incubator_id = ?"
        );
        $stmt->execute([$cooldownUntil->format('Y-m-d H:i:s'), $this->incubator_id]);

        $this->logger->logSessionEvent(
            $session['id'],
            $this->incubator_id,
            'cooldown_enter',
            $this->current_temp,
            $this->current_humidity,
            sprintf("Target temp reached (%.1f°C). Entering cooldown for %d minutes", $this->current_temp, self::COOLDOWN_DURATION_MINUTES)
        );

        return $this->returnCooldown($session, $control);
    }

    /**
     * Check if cooldown should end
     */
    private function shouldExitCooldown(array $control, float $target, float $current): bool {
        // Cooldown expired
        if ($control['cooldown_until'] && new DateTime($control['cooldown_until']) <= new DateTime()) {
            return $current <= $target;
        }

        // Temp dropped below target and cooldown time passed
        return $current <= $target - 0.5;
    }

    /**
     * Exit cooldown state
     */
    private function exitCooldown(array $session, array $control): array {
        $stmt = $this->pdo->prepare(
            "UPDATE controls_state 
             SET status = 'running', cooldown_until = NULL, relay_heater = 1
             WHERE incubator_id = ?"
        );
        $stmt->execute([$this->incubator_id]);

        $this->logger->logSessionEvent(
            $session['id'],
            $this->incubator_id,
            'cooldown_exit',
            $this->current_temp,
            $this->current_humidity,
            sprintf("Cooldown ended. Resuming heating. Temp: %.1f°C", $this->current_temp)
        );

        return $this->returnRunning($session, $control);
    }

    /**
     * Start a due schedule - create session and update controls
     */
    private function startDueSchedule(array $schedule, array $control): array {
        // Get target temperatures from schedule or from temperature_settings
        $target_temp = $schedule['target_temp'] ?? $this->getDefaultTargetTemp();
        $target_humidity = $schedule['target_humidity'] ?? $this->getDefaultTargetHumidity();

        // Get duration
        $duration_hours = $schedule['duration_hours'] ?? 21; // Default 21 hours for chicken

        // Start transaction
        $this->pdo->beginTransaction();

        try {
            // Create session
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, schedule_id, status, target_temp, target_humidity, 
                  duration_hours, auto_started)
                 VALUES (?, ?, 'running', ?, ?, ?, 1)"
            );
            $stmt->execute([
                $this->incubator_id,
                $schedule['id'],
                $target_temp,
                $target_humidity,
                $duration_hours
            ]);

            $session_id = $this->pdo->lastInsertId();

            // Update controls state
            $stmt = $this->pdo->prepare(
                "UPDATE controls_state 
                 SET session_id = ?, status = 'running', target_temp = ?, target_humidity = ?,
                     cooldown_until = NULL, safety_triggered = 'none',
                     relay_heater = 1, relay_swing = 1, relay_exhaust = 0
                 WHERE incubator_id = ?"
            );
            $stmt->execute([$session_id, $target_temp, $target_humidity, $this->incubator_id]);

            // Update schedule to running
            $stmt = $this->pdo->prepare(
                "UPDATE schedules 
                 SET status = 'running', auto_start = 1
                 WHERE id = ?"
            );
            $stmt->execute([$schedule['id']]);

            // Log session start
            $this->logger->logSessionEvent(
                $session_id,
                $this->incubator_id,
                'session_start',
                $this->current_temp,
                $this->current_humidity,
                sprintf("Auto-started from schedule. Duration: %.1f hours, Target: %.1f°C / %.0f%%", 
                        $duration_hours, $target_temp, $target_humidity)
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'status' => 'RUN',
                'session_id' => $session_id,
                'command' => [
                    'action' => 'start_session',
                    'session_id' => $session_id,
                    'target_temp' => (float)$target_temp,
                    'target_humidity' => (float)$target_humidity,
                    'duration_minutes' => (int)(((float)$duration_hours) * 60),
                    'relays' => [
                        'heater' => 1,
                        'swing' => 1,
                        'exhaust' => 0,
                        'fan' => 0
                    ]
                ]
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Persist reason why a due schedule failed to start.
     */
    private function logFailedScheduleStart(array $schedule, string $reason): void {
        $scheduleId = (int)($schedule['id'] ?? 0);
        $batchId = isset($schedule['batch_id']) && $schedule['batch_id'] !== null ? (int)$schedule['batch_id'] : null;
        $scheduledAt = trim((string)($schedule['scheduled_date'] ?? '') . ' ' . (string)($schedule['scheduled_time'] ?? ''));
        $details = sprintf(
            "Schedule #%d failed to auto-start on incubator #%d (scheduled at %s): %s",
            $scheduleId,
            $this->incubator_id,
            $scheduledAt !== '' ? $scheduledAt : 'N/A',
            $reason
        );

        error_log('[HeartbeatScheduler] ' . $details);
    $this->appendScheduleFailureLog('HeartbeatScheduler', $details);

        try {
            // Prefer storing reason in schedule notes when column exists.
            $this->pdo->prepare(
                "UPDATE schedules
                 SET status = 'failed', notes = ?
                 WHERE id = ? AND status = 'pending'"
            )->execute([$details, $scheduleId]);
        } catch (Exception $e) {
            $this->pdo->prepare(
                "UPDATE schedules
                 SET status = 'failed'
                 WHERE id = ? AND status = 'pending'"
            )->execute([$scheduleId]);
        }

        if ($batchId) {
            $this->pdo->prepare(
                "UPDATE batches
                 SET status = 'failed', terminated_at = COALESCE(terminated_at, NOW())
                 WHERE id = ? AND status IN ('scheduled', 'incubating')"
            )->execute([$batchId]);
        }

        $this->logger->logActivity('system', null, 'auto_schedule_start_failed', $details);
    }

    /**
     * Append schedule failure details to a plain text log file.
     */
    private function appendScheduleFailureLog(string $source, string $details): void {
        try {
            $logDir = dirname(self::SCHEDULE_FAILURE_LOG_PATH);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }

            $line = sprintf(
                "[%s] [%s] incubator=%d %s%s",
                date('Y-m-d H:i:s'),
                $source,
                $this->incubator_id,
                $details,
                PHP_EOL
            );
            @file_put_contents(self::SCHEDULE_FAILURE_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('[HeartbeatScheduler] Failed to write text log: ' . $e->getMessage());
        }
    }

    /**
     * Return running session state
     */
    private function returnRunning(array $session, array $control): array {
        return [
            'success' => true,
            'status' => 'RUN',
            'session_id' => $session['id'],
            'command' => [
                'action' => 'continue_session',
                'session_id' => $session['id'],
                'target_temp' => (float)($control['target_temp'] ?? $session['target_temp']),
                'target_humidity' => (float)($control['target_humidity'] ?? $session['target_humidity']),
                'relays' => [
                    'heater' => (int)$control['relay_heater'],
                    'swing' => (int)$control['relay_swing'],
                    'exhaust' => (int)$control['relay_exhaust'],
                    'fan' => (int)$control['relay_fan']
                ]
            ]
        ];
    }

    /**
     * Return cooldown state
     */
    private function returnCooldown(array $session, array $control): array {
        return [
            'success' => true,
            'status' => 'COOLDOWN',
            'session_id' => $session['id'],
            'command' => [
                'action' => 'cooldown',
                'session_id' => $session['id'],
                'cooldown_until' => $control['cooldown_until'],
                'relays' => [
                    'heater' => 0,
                    'swing' => 0,
                    'exhaust' => 0,
                    'fan' => 0
                ]
            ]
        ];
    }

    /**
     * Return idle state
     */
    private function returnIdle(): array {
        $stmt = $this->pdo->prepare(
            "UPDATE controls_state 
             SET status = 'idle', session_id = NULL, relay_heater = 0, relay_swing = 0
             WHERE incubator_id = ?"
        );
        $stmt->execute([$this->incubator_id]);

        return [
            'success' => true,
            'status' => 'STOP',
            'session_id' => null,
            'command' => [
                'action' => 'idle',
                'relays' => [
                    'heater' => 0,
                    'swing' => 0,
                    'exhaust' => 0,
                    'fan' => 0
                ]
            ]
        ];
    }

    /**
     * Get default target temperature from settings
     */
    private function getDefaultTargetTemp(): float {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT target_temperature FROM temperature_settings 
                 WHERE incubator_id = ? LIMIT 1"
            );
            $stmt->execute([$this->incubator_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && isset($result['target_temperature']) ? (float)$result['target_temperature'] : 37.5;
        } catch (Exception $e) {
            // Column may be missing or table not present; fall back to safe default
            error_log("[HeartbeatScheduler] getDefaultTargetTemp fallback: " . $e->getMessage());
            return 37.5;
        }
    }

    /**
     * Get default target humidity from settings
     */
    private function getDefaultTargetHumidity(): float {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT target_humidity FROM temperature_settings 
                 WHERE incubator_id = ? LIMIT 1"
            );
            $stmt->execute([$this->incubator_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && isset($result['target_humidity']) ? (float)$result['target_humidity'] : 60.0;
        } catch (Exception $e) {
            // Column may be missing or table not present; fall back to safe default
            error_log("[HeartbeatScheduler] getDefaultTargetHumidity fallback: " . $e->getMessage());
            return 60.0;
        }
    }
}

/**
 * Session Logger - handles event logging and activity logging
 */
class SessionLogger {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Log session event
     */
    public function logSessionEvent(
        int $session_id,
        int $incubator_id,
        string $event_type,
        ?float $temperature = null,
        ?float $humidity = null,
        ?string $message = null
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO session_logs 
             (session_id, incubator_id, event_type, temperature, humidity, message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $session_id,
            $incubator_id,
            $event_type,
            $temperature,
            $humidity,
            $message
        ]);
    }

    /**
     * Log activity
     */
    public function logActivity(string $role, ?int $user_id, string $action, string $details): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO activity_logs 
             (role, user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $role,
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'console'
        ]);
    }
}
