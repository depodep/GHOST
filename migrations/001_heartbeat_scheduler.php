<?php
/**
 * ============================================================
 *  GHOST Heartbeat Scheduler — Database Migration
 *  File: /GHOST/migrations/001_heartbeat_scheduler.php
 *
 *  Ensures all database tables and columns exist for the
 *  heartbeat scheduler system.
 *
 *  Run: php migrations/001_heartbeat_scheduler.php
 * ============================================================
 */

require_once __DIR__ . '/../includes/config.php';

class Migration {
    private $pdo;
    private $migrations = [];

    public function __construct() {
        $this->pdo = getDB();
    }

    public function runAll() {
        echo "\n=== GHOST Heartbeat Scheduler Database Migration ===\n\n";

        try {
            $this->createSessionsTable();
            $this->createControlsStateTable();
            $this->createSessionLogsTable();
            $this->updateSchedulesTable();

            echo "\n✓ All migrations completed successfully!\n\n";
            return true;

        } catch (Exception $e) {
            echo "\n✗ Migration failed: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    /**
     * Create sessions table
     */
    private function createSessionsTable() {
        echo "Creating sessions table...";

        $sql = "
            CREATE TABLE IF NOT EXISTS sessions (
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
                INDEX idx_incubator_id (incubator_id),
                INDEX idx_batch_id (batch_id),
                INDEX idx_schedule_id (schedule_id),
                INDEX idx_status (status),
                INDEX idx_started_at (started_at),
                FOREIGN KEY fk_sessions_incubator (incubator_id) 
                    REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        $this->pdo->exec($sql);
        echo " ✓\n";
    }

    /**
     * Create controls_state table
     */
    private function createControlsStateTable() {
        echo "Creating controls_state table...";

        $sql = "
            CREATE TABLE IF NOT EXISTS controls_state (
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
                INDEX idx_incubator_id (incubator_id),
                FOREIGN KEY fk_controls_incubator (incubator_id) 
                    REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        $this->pdo->exec($sql);
        echo " ✓\n";
    }

    /**
     * Create session_logs table
     */
    private function createSessionLogsTable() {
        echo "Creating session_logs table...";

        $sql = "
            CREATE TABLE IF NOT EXISTS session_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                incubator_id INT NOT NULL,
                event_type ENUM('session_start', 'session_end', 'cooldown_enter', 'cooldown_exit', 
                                'safety_alert', 'duration_expired', 'manual_stop', 'device_offline') NOT NULL,
                temperature DECIMAL(5,2) DEFAULT NULL,
                humidity DECIMAL(5,2) DEFAULT NULL,
                message TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_id (session_id),
                INDEX idx_incubator_id (incubator_id),
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                FOREIGN KEY fk_session_logs_session (session_id) 
                    REFERENCES sessions(id) ON DELETE CASCADE,
                FOREIGN KEY fk_session_logs_incubator (incubator_id) 
                    REFERENCES incubators(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        $this->pdo->exec($sql);
        echo " ✓\n";
    }

    /**
     * Update schedules table with new columns
     */
    private function updateSchedulesTable() {
        echo "Checking schedules table columns...";

        $columns = [];
        $result = $this->pdo->query("SHOW COLUMNS FROM schedules");
        foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }

        $added = 0;

        if (!isset($columns['duration_hours'])) {
            echo "\n  Adding duration_hours column...";
            $this->pdo->exec(
                "ALTER TABLE schedules 
                 ADD COLUMN duration_hours DECIMAL(5,2) DEFAULT NULL 
                 COMMENT 'Duration in hours for auto-started sessions'"
            );
            $added++;
            echo " ✓";
        }

        if (!isset($columns['auto_start'])) {
            echo "\n  Adding auto_start column...";
            $this->pdo->exec(
                "ALTER TABLE schedules 
                 ADD COLUMN auto_start TINYINT(1) DEFAULT 0 
                 COMMENT 'Whether schedule was auto-started'"
            );
            $added++;
            echo " ✓";
        }

        if (!isset($columns['run_now'])) {
            echo "\n  Adding run_now column...";
            $this->pdo->exec(
                "ALTER TABLE schedules 
                 ADD COLUMN run_now TINYINT(1) DEFAULT 0 
                 COMMENT 'Force schedule to run immediately on next heartbeat'"
            );
            $added++;
            echo " ✓";
        }

        if ($added === 0) {
            echo " (all columns present)";
        }
        echo "\n";
    }
}

// Run migration only when this file is executed directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $migration = new Migration();
    $success = $migration->runAll();
    exit($success ? 0 : 1);
}
