<?php

require_once __DIR__ . '/ScheduleChecker.php';

class SchedulerWorker {
    private PDO $pdo;
    private string $lockFilePath;
    private int $executionIntervalSeconds;
    private int $lockTtlSeconds;
    private $lockHandle = null;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->executionIntervalSeconds = max(1, (int)SCHEDULER_EXECUTION_INTERVAL_SECONDS);
        $this->lockTtlSeconds = max($this->executionIntervalSeconds * 2, (int)SCHEDULER_LOCK_TTL_SECONDS);
        $this->lockFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ghost_scheduler_worker.lock';
    }

    public function run(string $source = 'web-poll', array $context = []): array {
        $this->ensureSchema();
        $state = $this->getStateRow();

        if ($this->isCooldownActive($state)) {
            return array(
                'success' => true,
                'status' => 'skipped',
                'reason' => 'cooldown_active',
                'next_run_after' => $state['cooldown_until'] ?? null,
                'state' => $state
            );
        }

        if (!$this->acquireLock()) {
            $this->recordAttempt('busy', $source, 0, 'scheduler lock held', $context);
            return array(
                'success' => true,
                'status' => 'busy',
                'reason' => 'lock_unavailable',
                'state' => $this->getStateRow()
            );
        }

        $runId = $this->createRunLog($source, $context);
        $startedAt = microtime(true);

        try {
            $this->markRunning($source, $runId);
            $this->pdo->beginTransaction();

            $changes = $this->processTick();

            $this->pdo->commit();

            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $this->markFinished('idle', 'completed', $runId, $durationMs, $changes);
            $this->touchRunMarker();

            return array(
                'success' => true,
                'status' => 'ok',
                'run_id' => $runId,
                'duration_ms' => $durationMs,
                'changes' => $changes,
                'state' => $this->getStateRow()
            );
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $this->markFinished('error', $e->getMessage(), $runId, $durationMs, array());

            return array(
                'success' => false,
                'status' => 'error',
                'run_id' => $runId,
                'error' => $e->getMessage(),
                'state' => $this->getStateRow()
            );
        } finally {
            $this->releaseLock();
        }
    }

    private function processTick(): array {
        $checker = new ScheduleChecker($this->pdo, SCHEDULE_OFFLINE_GRACE_SECONDS);
        $scheduleChanges = $checker->processPendingSchedules(null);

        $cleanupChanges = array(
            'stale_sessions' => 0,
            'expired_batches' => 0
        );

        $cleanupChanges['stale_sessions'] = $this->expireStaleSessions();
        $cleanupChanges['expired_batches'] = $this->expireScheduledBatches();

        return array_merge($scheduleChanges, $cleanupChanges);
    }

    private function expireStaleSessions(): int {
        $count = 0;
        $timeoutSeconds = SCHEDULE_OFFLINE_GRACE_SECONDS;
        $stmt = $this->pdo->prepare(
            "SELECT s.id AS session_id, s.batch_id, s.incubator_id, s.started_at, h.last_seen
             FROM sessions s
             JOIN hardware_state h ON s.incubator_id = h.incubator_id
             WHERE s.status = 'running'"
        );
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $startedAt = strtotime((string)$row['started_at']);
            $lastSeen = !empty($row['last_seen']) ? strtotime((string)$row['last_seen']) : 0;

            if ($lastSeen === 0 || $lastSeen < ($startedAt + $timeoutSeconds)) {
                $this->pdo->prepare("UPDATE sessions SET status = 'interrupted', ended_at = NOW(), reason_ended = ? WHERE id = ?")
                    ->execute(array('no_heartbeat', $row['session_id']));

                if (!empty($row['batch_id'])) {
                    $this->pdo->prepare("UPDATE batches SET status = 'failed' WHERE id = ?")->execute(array($row['batch_id']));
                }

                $this->pdo->prepare("UPDATE hardware_state SET session_status = 'failed', last_server_sync = NOW() WHERE incubator_id = ?")
                    ->execute(array($row['incubator_id']));

                $this->pdo->prepare("INSERT INTO session_logs (session_id, incubator_id, event_type, temperature, humidity, message) VALUES (?,?,?,?,?,?)")
                    ->execute(array($row['session_id'], $row['incubator_id'], 'device_offline', null, null, 'No heartbeat received within timeout after session start'));

                $count++;
            }
        }

        return $count;
    }

    private function expireScheduledBatches(): int {
        $count = 0;
        $timeoutSeconds = SCHEDULE_OFFLINE_GRACE_SECONDS;
        $stmt = $this->pdo->prepare("SELECT id, notes, incubator_id FROM batches WHERE status = 'scheduled'");
        $stmt->execute();
        $now = time();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scheduledStart = null;
            if (!empty($row['notes']) && preg_match('/SCHEDULED_START:\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', (string)$row['notes'], $matches)) {
                $scheduledStart = strtotime($matches[1]);
            }

            if ($scheduledStart && ($scheduledStart + $timeoutSeconds) <= $now) {
                $this->pdo->prepare("UPDATE batches SET status = 'failed' WHERE id = ? AND status = 'scheduled'")
                    ->execute(array($row['id']));
                $this->pdo->prepare("UPDATE schedules SET status = 'failed' WHERE batch_id = ? AND status = 'pending'")
                    ->execute(array($row['id']));
                $count++;
            }
        }

        return $count;
    }

    private function ensureSchema(): void {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS scheduler_state (
                id INT NOT NULL PRIMARY KEY DEFAULT 1,
                status VARCHAR(24) NOT NULL DEFAULT 'idle',
                last_source VARCHAR(64) DEFAULT NULL,
                last_message TEXT DEFAULT NULL,
                last_run_started_at TIMESTAMP NULL DEFAULT NULL,
                last_run_finished_at TIMESTAMP NULL DEFAULT NULL,
                last_success_at TIMESTAMP NULL DEFAULT NULL,
                last_error_at TIMESTAMP NULL DEFAULT NULL,
                last_duration_ms INT DEFAULT NULL,
                run_count INT NOT NULL DEFAULT 0,
                lock_owner VARCHAR(191) DEFAULT NULL,
                lock_expires_at TIMESTAMP NULL DEFAULT NULL,
                cooldown_until TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS scheduler_runs (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                run_token VARCHAR(64) NOT NULL,
                source VARCHAR(64) NOT NULL,
                status VARCHAR(24) NOT NULL,
                message TEXT DEFAULT NULL,
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL DEFAULT NULL,
                duration_ms INT DEFAULT NULL,
                details_json LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_scheduler_runs_status (status),
                INDEX idx_scheduler_runs_started_at (started_at),
                INDEX idx_scheduler_runs_source (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->pdo->prepare(
            "INSERT INTO scheduler_state (id, status, run_count)
             VALUES (1, 'idle', 0)
             ON DUPLICATE KEY UPDATE id = id"
        )->execute();
    }

    private function getStateRow(): array {
        $stmt = $this->pdo->query("SELECT * FROM scheduler_state WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : array();
        return $row ?: array(
            'status' => 'idle',
            'last_source' => null,
            'last_message' => null,
            'last_run_started_at' => null,
            'last_run_finished_at' => null,
            'last_success_at' => null,
            'last_error_at' => null,
            'last_duration_ms' => null,
            'run_count' => 0,
            'lock_owner' => null,
            'lock_expires_at' => null,
            'cooldown_until' => null
        );
    }

    private function isCooldownActive(array $state): bool {
        if (empty($state['cooldown_until'])) {
            return false;
        }

        $cooldownTs = strtotime((string)$state['cooldown_until']);
        return $cooldownTs !== false && $cooldownTs > time();
    }

    private function acquireLock(): bool {
        $handle = @fopen($this->lockFilePath, 'c');
        if (!$handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        $this->lockHandle = $handle;
        return true;
    }

    private function releaseLock(): void {
        if (is_resource($this->lockHandle)) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
        }

        $this->lockHandle = null;
    }

    private function createRunLog(string $source, array $context): string {
        $runToken = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare(
            "INSERT INTO scheduler_runs (run_token, source, status, message, details_json)
             VALUES (?, ?, 'running', ?, ?)"
        );
        $stmt->execute(array(
            $runToken,
            $source,
            'scheduler tick started',
            json_encode($context, JSON_UNESCAPED_SLASHES)
        ));

        return $runToken;
    }

    private function markRunning(string $source, string $runToken): void {
        $owner = php_sapi_name() . ':' . getmypid();
        $stmt = $this->pdo->prepare(
            "UPDATE scheduler_state
             SET status = 'running', last_source = ?, last_message = ?, last_run_started_at = NOW(),
                 lock_owner = ?, lock_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), run_count = run_count + 1
             WHERE id = 1"
        );
        $stmt->execute(array($source, $runToken, $owner, $this->lockTtlSeconds));
    }

    private function markFinished(string $status, string $message, string $runToken, int $durationMs, array $details): void {
        $this->pdo->prepare(
            "UPDATE scheduler_state
             SET status = ?, last_message = ?, last_run_finished_at = NOW(),
                 last_duration_ms = ?,
                 last_success_at = CASE WHEN ? = 'error' THEN last_success_at ELSE NOW() END,
                 last_error_at = CASE WHEN ? = 'error' THEN NOW() ELSE last_error_at END,
                 lock_owner = NULL, lock_expires_at = NULL,
                 cooldown_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE id = 1"
        )->execute(array($status, $message, $durationMs, $status, $status, $this->executionIntervalSeconds));

        $this->pdo->prepare(
            "UPDATE scheduler_runs
             SET status = ?, message = ?, finished_at = NOW(), duration_ms = ?, details_json = ?
             WHERE run_token = ?"
        )->execute(array(
            $status,
            $message,
            $durationMs,
            json_encode($details, JSON_UNESCAPED_SLASHES),
            $runToken
        ));
    }

    private function recordAttempt(string $status, string $source, int $durationMs, string $message, array $details): void {
        $this->ensureSchema();
        $this->pdo->prepare(
            "INSERT INTO scheduler_runs (run_token, source, status, message, started_at, finished_at, duration_ms, details_json)
             VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)"
        )->execute(array(
            bin2hex(random_bytes(12)),
            $source,
            $status,
            $message,
            $durationMs,
            json_encode($details, JSON_UNESCAPED_SLASHES)
        ));
    }

    private function touchRunMarker(): void {
        $marker = __DIR__ . '/../scripts/run_schedules.last';
        @file_put_contents($marker, gmdate('c'));
    }
}