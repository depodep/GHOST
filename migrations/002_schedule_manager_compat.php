<?php
/**
 * ============================================================
 *  GHOST Schedule Manager Compatibility Migration
 *  File: /GHOST/migrations/002_schedule_manager_compat.php
 *
 *  Ensures database schema is compatible with schedule_manager.py:
 *   - incubators.device_prototype_status
 *   - schedules.notes
 *   - schedules.status includes 'failed' when ENUM
 *   - activity_logs.role includes 'system'
 *   - activity_logs.user_id allows NULL for system-generated logs
 *
 *  Run: php migrations/002_schedule_manager_compat.php
 * ============================================================
 */

require_once __DIR__ . '/../includes/config.php';

class ScheduleManagerCompatMigration {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): bool {
        echo "\n=== Schedule Manager Compatibility Migration ===\n\n";

        try {
            $this->addDevicePrototypeStatusColumn();
            $this->addScheduleNotesColumn();
            $this->ensureFailedStatusInSchedules();
            $this->ensureActivityLogsSystemRole();

            echo "\n✓ Migration completed successfully.\n\n";
            return true;
        } catch (Throwable $e) {
            echo "\n✗ Migration failed: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    private function addDevicePrototypeStatusColumn(): void {
        echo "Checking incubators.device_prototype_status...";

        if (!$this->columnExists('incubators', 'device_prototype_status')) {
            $this->pdo->exec(
                "ALTER TABLE incubators
                 ADD COLUMN device_prototype_status VARCHAR(20) NOT NULL DEFAULT 'unknown'
                 COMMENT 'Prototype connectivity hint for scheduler'"
            );
            echo " added ✓\n";
        } else {
            echo " exists ✓\n";
        }
    }

    private function addScheduleNotesColumn(): void {
        echo "Checking schedules.notes...";

        if (!$this->columnExists('schedules', 'notes')) {
            $this->pdo->exec(
                "ALTER TABLE schedules
                 ADD COLUMN notes TEXT NULL
                 COMMENT 'Failure/details notes for scheduler actions'"
            );
            echo " added ✓\n";
        } else {
            echo " exists ✓\n";
        }
    }

    private function ensureFailedStatusInSchedules(): void {
        echo "Checking schedules.status supports 'failed'...";

        $meta = $this->getColumnMeta('schedules', 'status');
        if (!$meta) {
            echo " skipped (status column not found) ✓\n";
            return;
        }

        $type = strtolower((string)$meta['Type']);

        // If status is not ENUM (e.g., VARCHAR), nothing to alter.
        if (strpos($type, 'enum(') !== 0) {
            echo " non-enum (no change needed) ✓\n";
            return;
        }

        $values = $this->parseEnumValues($type);
        if (in_array('failed', $values, true)) {
            echo " already present ✓\n";
            return;
        }

        $values[] = 'failed';
        $enumSql = "ENUM('" . implode("','", array_map([$this, 'escapeEnumLiteral'], $values)) . "')";
        $nullable = (strtoupper((string)$meta['Null']) === 'YES') ? 'NULL' : 'NOT NULL';
        $default = $meta['Default'];
        if ($default === null) {
            $default = 'pending';
        }
        if (!in_array((string)$default, $values, true)) {
            $default = 'pending';
        }
        $defaultSql = $default !== null
            ? " DEFAULT '" . str_replace("'", "''", (string)$default) . "'"
            : '';

        $this->pdo->exec(
            "ALTER TABLE schedules
             MODIFY COLUMN status {$enumSql} {$nullable}{$defaultSql}"
        );

        echo " updated ✓\n";
    }

    private function ensureActivityLogsSystemRole(): void {
        echo "Checking activity_logs role/user_id compatibility...";

        if (!$this->columnExists('activity_logs', 'role') || !$this->columnExists('activity_logs', 'user_id')) {
            echo " skipped (activity_logs table/columns missing) ✓\n";
            return;
        }

        $roleMeta = $this->getColumnMeta('activity_logs', 'role');
        $roleType = strtolower((string)($roleMeta['Type'] ?? ''));
        if (strpos($roleType, 'enum(') === 0) {
            $roleValues = $this->parseEnumValues($roleType);
            if (!in_array('system', $roleValues, true)) {
                $roleValues[] = 'system';
                $enumSql = "ENUM('" . implode("','", array_map([$this, 'escapeEnumLiteral'], $roleValues)) . "')";
                $this->pdo->exec(
                    "ALTER TABLE activity_logs
                     MODIFY COLUMN role {$enumSql} NOT NULL"
                );
            }
        }

        // System-generated logs in the code use user_id = NULL.
        $this->pdo->exec(
            "ALTER TABLE activity_logs
             MODIFY COLUMN user_id INT NULL"
        );

        echo " updated ✓\n";
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
        return (int)$stmt->fetchColumn() > 0;
    }

    private function getColumnMeta(string $table, string $column): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT
                COLUMN_NAME AS Field,
                COLUMN_TYPE AS Type,
                IS_NULLABLE AS `Null`,
                COLUMN_DEFAULT AS `Default`
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function parseEnumValues(string $enumType): array {
        // enum('a','b','c') -> ['a','b','c']
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $enumType, $matches);
        $vals = [];
        foreach ($matches[1] as $raw) {
            $vals[] = str_replace("\\\\'", "'", $raw);
        }
        return $vals;
    }

    private function escapeEnumLiteral(string $value): string {
        return str_replace("'", "''", $value);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $pdo = getDB();
    $migration = new ScheduleManagerCompatMigration($pdo);
    $ok = $migration->run();
    exit($ok ? 0 : 1);
}
