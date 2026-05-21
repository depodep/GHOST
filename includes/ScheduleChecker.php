<?php

/**
 * Native PHP replacement for schedule_manager.py one-pass checks.
 *
 * Responsibilities:
 * - Process due pending schedules.
 * - Fail schedules when a session is already running.
 * - Fail schedules after offline grace period.
 * - Auto-start sessions when device is online.
 */
class ScheduleChecker {
    private PDO $pdo;
    private int $gracePeriodSeconds;
    private bool $supportsScheduleNotes;
    private bool $supportsSystemActivityRole;
    private bool $supportsNullActivityUserId;
    private const SCHEDULE_FAILURE_LOG_PATH = __DIR__ . '/../logs/schedule_start_failures.log';

    public function __construct(PDO $pdo, int $gracePeriodSeconds = 10) {
        $this->pdo = $pdo;
        $this->gracePeriodSeconds = max(1, $gracePeriodSeconds);
        $this->supportsScheduleNotes = $this->columnExists('schedules', 'notes');
        $this->supportsSystemActivityRole = $this->enumContains('activity_logs', 'role', 'system');
        $this->supportsNullActivityUserId = $this->isNullable('activity_logs', 'user_id');
    }

    public function processPendingSchedules(?int $incubatorId = null): array {
        error_log(sprintf("[ScheduleChecker] processPendingSchedules invoked for incubator=%s", $incubatorId === null ? 'ALL' : $incubatorId));
        $changes = [
            'failed_batches' => 0,
            'promoted_batches' => 0,
            'failed_schedules' => 0,
            'promoted_schedules' => 0,
            'started_sessions' => 0,
            'waiting_schedules' => 0
        ];

        $this->ensureSessionSchema();
        $this->ensureBatchColumns();

        foreach ($this->getDuePendingSchedules($incubatorId) as $schedule) {
            error_log(sprintf("[ScheduleChecker] Evaluating schedule id=%d incubator=%d batch=%s scheduled=%s %s", (int)$schedule['schedule_id'], (int)$schedule['incubator_id'], $schedule['batch_id'] ?? 'NULL', $schedule['scheduled_date'] ?? 'N/A', $schedule['scheduled_time'] ?? 'N/A'));
            $scheduleId = (int)$schedule['schedule_id'];
            $incId = (int)$schedule['incubator_id'];
            $batchId = !empty($schedule['batch_id']) ? (int)$schedule['batch_id'] : null;

            if ($this->isSessionAlreadyRunning($incId)) {
                error_log(sprintf("[ScheduleChecker] Skipping schedule %d because a session is already running on incubator %d", $scheduleId, $incId));
                $this->appendScheduleFailureLog($incId, sprintf('Schedule #%d skipped: session already running on incubator %d', $scheduleId, $incId));
                if ($this->markScheduleFailed($scheduleId, "Session already running on incubator {$incId}")) {
                    $changes['failed_schedules']++;
                }
                if ($batchId && $this->markBatchFailed($batchId)) {
                    $changes['failed_batches']++;
                }
                continue;
            }

            $device = $this->getDeviceState($incId);
            error_log(sprintf("[ScheduleChecker] Device state for incubator %d => online=%s last_seen=%s", $incId, $device['online'] ? '1' : '0', $device['last_seen'] === null ? 'NULL' : date('c', $device['last_seen'])));
            if (!$device['online']) {
                if ($this->isOutsideGracePeriod($device['last_seen'])) {
                    $reason = "Device offline after grace period ({$this->gracePeriodSeconds} sec)";
                    error_log(sprintf("[ScheduleChecker] Failing schedule %d because device offline and outside grace period", $scheduleId));
                    $this->appendScheduleFailureLog($incId, sprintf('Schedule #%d failed: %s', $scheduleId, $reason));
                    if ($this->markScheduleFailed($scheduleId, $reason)) {
                        $changes['failed_schedules']++;
                    }
                    if ($batchId && $this->markBatchFailed($batchId)) {
                        $changes['failed_batches']++;
                    }
                } else {
                    // Device is offline but within grace window -> mark batch as waiting_for_device
                    if ($batchId) {
                        try {
                            $stmt = $this->pdo->prepare("SELECT waiting_for_device_until FROM batches WHERE id = ? LIMIT 1");
                            $stmt->execute([$batchId]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!$row || empty($row['waiting_for_device_until'])) {
                                $stmt2 = $this->pdo->prepare("UPDATE batches SET waiting_for_device_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?");
                                $stmt2->execute([$this->gracePeriodSeconds, $batchId]);
                                $changes['waiting_schedules']++;
                            } else {
                                $waitingTs = strtotime($row['waiting_for_device_until']);
                                if ($waitingTs !== false && $waitingTs <= time()) {
                                    $reason = "Device did not come online within grace period ({$this->gracePeriodSeconds} sec)";
                                    $this->appendScheduleFailureLog($incId, sprintf('Schedule #%d failed: %s', $scheduleId, $reason));
                                    if ($this->markScheduleFailed($scheduleId, $reason)) {
                                        $changes['failed_schedules']++;
                                    }
                                    if ($batchId && $this->markBatchFailed($batchId)) {
                                        $changes['failed_batches']++;
                                    }
                                } else {
                                    $changes['waiting_schedules']++;
                                }
                            }
                        } catch (Exception $e) {
                            error_log('[ScheduleChecker] waiting_for_device handling error: ' . $e->getMessage());
                            $changes['waiting_schedules']++;
                        }
                    } else {
                        $changes['waiting_schedules']++;
                    }
                }
                continue;
            }

            $created = $this->createSession(
                $incId,
                $batchId,
                $scheduleId,
                (float)$schedule['target_temp'],
                (float)$schedule['target_humidity'],
                $this->extractDurationHours($schedule)
            );

            if ($created) {
                error_log(sprintf("[ScheduleChecker] Created session for schedule=%d incubator=%d", $scheduleId, $incId));
            } else {
                error_log(sprintf("[ScheduleChecker] Failed to create session record for schedule=%d incubator=%d", $scheduleId, $incId));
                $this->appendScheduleFailureLog($incId, sprintf('Schedule #%d failed: could not create session record', $scheduleId));
            }

            if (!$created) {
                if ($this->markScheduleFailed($scheduleId, 'Failed to create session record')) {
                    $changes['failed_schedules']++;
                }
                if ($batchId && $this->markBatchFailed($batchId)) {
                    $changes['failed_batches']++;
                }
                continue;
            }

            $this->queueStartCommand($incId, $scheduleId);
            if ($this->markScheduleDone($scheduleId)) {
                $changes['promoted_schedules']++;
            }
            if ($batchId && $this->markBatchIncubating($batchId)) {
                $changes['promoted_batches']++;
            }
            $changes['started_sessions']++;
        }

        return $changes;
    }

    private function getDuePendingSchedules(?int $incubatorId = null): array {
                $sql = "SELECT
                                        s.id AS schedule_id,
                                        s.incubator_id,
                                        s.batch_id,
                                        s.description,
                                        s.duration_hours,
                                        COALESCE(s.target_temp, ts.target_temp, 37.50) AS target_temp,
                                        COALESCE(s.target_humidity, ts.target_humidity, 55.00) AS target_humidity
                                FROM schedules s
                                LEFT JOIN temperature_settings ts ON ts.incubator_id = s.incubator_id
                                WHERE s.status = 'pending'
                                    AND CONCAT(s.scheduled_date, ' ', s.scheduled_time) <= NOW()";
        $params = [];
        if ($incubatorId !== null) {
            $sql .= ' AND s.incubator_id = ?';
            $params[] = $incubatorId;
        }
        $sql .= ' ORDER BY s.scheduled_date ASC, s.scheduled_time ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getDeviceState(int $incubatorId): array {
        $stmt = $this->pdo->prepare(
            "SELECT device_status, last_seen
             FROM hardware_state
             WHERE incubator_id = ?
             LIMIT 1"
        );
        $stmt->execute([$incubatorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['online' => false, 'last_seen' => null];
        }

        $lastSeen = !empty($row['last_seen']) ? strtotime((string)$row['last_seen']) : null;
        $isFreshSeen = $lastSeen !== null && (time() - $lastSeen) <= $this->gracePeriodSeconds;
        $isOnline = (($row['device_status'] ?? 'offline') === 'online') || $isFreshSeen;

        return [
            'online' => $isOnline,
            'last_seen' => $lastSeen
        ];
    }

    private function isOutsideGracePeriod(?int $lastSeenTimestamp): bool {
        if ($lastSeenTimestamp === null) {
            return true;
        }
        return (time() - $lastSeenTimestamp) > $this->gracePeriodSeconds;
    }

    private function isSessionAlreadyRunning(int $incubatorId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM sessions
             WHERE incubator_id = ? AND status = 'running'"
        );
        $stmt->execute([$incubatorId]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    private function createSession(
        int $incubatorId,
        ?int $batchId,
        int $scheduleId,
        float $targetTemp,
        float $targetHumidity,
        ?float $durationHours
    ): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sessions
                (incubator_id, batch_id, schedule_id, status, target_temp, target_humidity, duration_hours, auto_started)
             VALUES
                (?, ?, ?, 'running', ?, ?, ?, 1)"
        );

        $ok = $stmt->execute([
            $incubatorId,
            $batchId,
            $scheduleId,
            $targetTemp,
            $targetHumidity,
            $durationHours
        ]);

        if ($ok) {
            $this->logScheduleEvent($scheduleId, 'session_started', "Session auto-started on incubator {$incubatorId}");
        }
        return $ok;
    }

    private function queueStartCommand(int $incubatorId, int $scheduleId): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO hardware_commands (incubator_id, command, issued_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$incubatorId, "start_session_{$scheduleId}"]);
        $this->logScheduleEvent($scheduleId, 'session_start_command_sent', "Auto-start command queued for incubator {$incubatorId}");
    }

    private function markScheduleDone(int $scheduleId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE schedules
             SET status = 'done'
             WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$scheduleId]) && $stmt->rowCount() > 0;
    }

    private function markScheduleFailed(int $scheduleId, string $reason): bool {
        if ($this->supportsScheduleNotes) {
            $stmt = $this->pdo->prepare(
                "UPDATE schedules
                 SET status = 'failed', notes = ?
                 WHERE id = ? AND status = 'pending'"
            );
            $ok = $stmt->execute([$reason, $scheduleId]) && $stmt->rowCount() > 0;
        } else {
            $stmt = $this->pdo->prepare(
                "UPDATE schedules
                 SET status = 'failed'
                 WHERE id = ? AND status = 'pending'"
            );
            $ok = $stmt->execute([$scheduleId]) && $stmt->rowCount() > 0;
        }

        if ($ok) {
            $this->logScheduleEvent($scheduleId, 'failed_to_start', $reason);
        }
        return $ok;
    }

    private function markBatchIncubating(int $batchId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE batches
             SET status = 'incubating', waiting_for_device_until = NULL
             WHERE id = ? AND status = 'scheduled'"
        );
        return $stmt->execute([$batchId]) && $stmt->rowCount() > 0;
    }

    private function markBatchFailed(int $batchId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE batches
             SET status = 'failed', terminated_at = COALESCE(terminated_at, NOW())
             WHERE id = ? AND status IN ('scheduled', 'incubating')"
        );
        return $stmt->execute([$batchId]) && $stmt->rowCount() > 0;
    }

    private function logScheduleEvent(int $scheduleId, string $eventType, string $message): void {
        $role = $this->supportsSystemActivityRole ? 'system' : 'admin';
        $userId = $this->supportsNullActivityUserId ? null : 1;

        $stmt = $this->pdo->prepare(
            "INSERT INTO activity_logs (role, user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?, '127.0.0.1')"
        );

        $stmt->execute([
            $role,
            $userId,
            $eventType,
            "Schedule #{$scheduleId}: {$message}"
        ]);
    }

    private function ensureSessionSchema(): void {
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
    }

        /**
         * Ensure batches has waiting_for_device_until column used for waiting state.
         */
        private function ensureBatchColumns(): void {
            $exists = $this->columnExists('batches', 'waiting_for_device_until');
            if (!$exists) {
                try {
                    $this->pdo->exec("ALTER TABLE batches ADD COLUMN waiting_for_device_until TIMESTAMP NULL DEFAULT NULL");
                } catch (Exception $e) {
                    // If ALTER fails (permissions), ignore - runtime logic will still work without persistent column
                    error_log('[ScheduleChecker] Could not add waiting_for_device_until column: ' . $e->getMessage());
                }
            }
        }

    private function extractDurationHours(array $schedule): ?float {
        if (isset($schedule['duration_hours']) && $schedule['duration_hours'] !== null && $schedule['duration_hours'] !== '') {
            return max(0.0, (float)$schedule['duration_hours']);
        }

        if (!empty($schedule['description'])) {
            $decoded = json_decode((string)$schedule['description'], true);
            if (is_array($decoded) && isset($decoded['session_duration_sec'])) {
                return max(0.0, (float)$decoded['session_duration_sec'] / 3600.0);
            }
        }

        return null;
    }

    private function columnExists(string $table, string $column): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    private function enumContains(string $table, string $column, string $value): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        $columnType = strtolower((string)$stmt->fetchColumn());
        if ($columnType === '') {
            return false;
        }
        return strpos($columnType, "'" . strtolower($value) . "'") !== false;
    }

    private function isNullable(string $table, string $column): bool {
        $stmt = $this->pdo->prepare(
            "SELECT IS_NULLABLE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        return strtoupper((string)$stmt->fetchColumn()) === 'YES';
    }

    private function appendScheduleFailureLog(int $incubatorId, string $details): void {
        $logDir = dirname(self::SCHEDULE_FAILURE_LOG_PATH);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf(
            "[%s] [ScheduleChecker] incubator=%d %s%s",
            date('Y-m-d H:i:s'),
            $incubatorId,
            $details,
            PHP_EOL
        );

        @file_put_contents(self::SCHEDULE_FAILURE_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    }
}
