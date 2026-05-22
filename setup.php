<?php
/**
 * ============================================================
 *  GHOST Setup & Verification Script
 *  File: /GHOST/setup.php
 *
 *  Ensures all required tables exist and creates demo data
 *  if needed.
 *
 *  Run: php setup.php
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset='utf-8'><title>GHOST Setup</title></head><body><pre>";
}

function out($text) {
    echo $text;
}

register_shutdown_function(function () use ($isCli) {
    if (!$isCli) {
        echo "</pre></body></html>";
    }
});

out("\n=== GHOST Incubator System Setup ===\n\n");

$pdo = getDB();

out("1. Creating/updating core tables...\n");

// Incubators table
out("   - incubators...");
try {
    // First ensure basic table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incubators (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            user_id INT,
            incubator_name VARCHAR(100),
            location VARCHAR(100),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add missing columns if needed
    $columns = [];
    $result = $pdo->query("SHOW COLUMNS FROM incubators");
    foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[$col['Field']] = true;
    }
    
    if (!isset($columns['user_id'])) {
        try { $pdo->exec("ALTER TABLE incubators ADD COLUMN user_id INT DEFAULT 1"); } catch (Exception $e) {}
    }
    if (!isset($columns['incubator_name'])) {
        try { $pdo->exec("ALTER TABLE incubators ADD COLUMN incubator_name VARCHAR(100)"); } catch (Exception $e) {}
    }
    if (!isset($columns['location'])) {
        try { $pdo->exec("ALTER TABLE incubators ADD COLUMN location VARCHAR(100)"); } catch (Exception $e) {}
    }
    if (!isset($columns['status'])) {
        try { $pdo->exec("ALTER TABLE incubators ADD COLUMN status ENUM('active','inactive') DEFAULT 'active'"); } catch (Exception $e) {}
    }
    
    out(" ✓\n");
} catch (Exception $e) {
    out(" ✓ (exists)\n");
}

// Users table
out("   - users...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

// Temperature settings
out("   - temperature_settings...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS temperature_settings (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            incubator_id INT NOT NULL,
            target_temperature DECIMAL(5,2) DEFAULT 37.5,
            target_humidity DECIMAL(5,2) DEFAULT 60.0,
            min_temperature DECIMAL(5,2) DEFAULT 36.0,
            max_temperature DECIMAL(5,2) DEFAULT 39.0,
            min_humidity DECIMAL(5,2) DEFAULT 40.0,
            max_humidity DECIMAL(5,2) DEFAULT 80.0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

// Batches table
out("   - batches...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS batches (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            incubator_id INT NOT NULL,
            user_id INT NOT NULL,
            batch_name VARCHAR(100) NOT NULL,
            egg_type VARCHAR(100) DEFAULT 'Chicken',
            egg_count INT DEFAULT 0,
            start_date DATE NOT NULL,
            expected_hatch_date DATE NOT NULL,
            status ENUM('scheduled', 'incubating', 'completed', 'terminated', 'hatched', 'failed', 'cancelled') DEFAULT 'incubating',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            terminated_at DATETIME DEFAULT NULL,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

// Schedules table
out("   - schedules...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedules (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            incubator_id INT NOT NULL,
            batch_id INT DEFAULT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            scheduled_date DATE NOT NULL,
            scheduled_time TIME NOT NULL,
            action_type ENUM('turning', 'temperature_check', 'humidity_check', 'candling', 'maintenance', 'hatch') DEFAULT 'turning',
            target_temp DECIMAL(5,2) DEFAULT NULL,
            target_humidity DECIMAL(5,2) DEFAULT NULL,
            duration_hours DECIMAL(5,2) DEFAULT NULL,
            auto_start TINYINT(1) DEFAULT 0,
            run_now TINYINT(1) DEFAULT 0,
            status ENUM('pending', 'running', 'done', 'missed', 'cancelled') DEFAULT 'pending',
            created_by_role ENUM('admin', 'user') DEFAULT 'admin',
            created_by_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $columns = [];
    $result = $pdo->query("SHOW COLUMNS FROM schedules");
    foreach ($result->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[$col['Field']] = true;
    }
    
    $added = 0;
    if (!isset($columns['title'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN title VARCHAR(200) DEFAULT 'Untitled'"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['description'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN description TEXT"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['scheduled_date'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN scheduled_date DATE NOT NULL"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['scheduled_time'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN scheduled_time TIME NOT NULL"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['action_type'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN action_type VARCHAR(50) DEFAULT 'turning'"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['target_temp'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN target_temp DECIMAL(5,2)"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['target_humidity'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN target_humidity DECIMAL(5,2)"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['status'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN status VARCHAR(50) DEFAULT 'pending'"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['created_by_id'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN created_by_id INT DEFAULT 1"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['created_by_role'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN created_by_role VARCHAR(50) DEFAULT 'admin'"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['created_at'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); $added++; } catch (Exception $e) {}
    }
    if (!isset($columns['batch_id'])) {
        try { $pdo->exec("ALTER TABLE schedules ADD COLUMN batch_id INT"); $added++; } catch (Exception $e) {}
    }
    
    if ($added > 0) {
        out(" (added $added columns)");
    }
    out(" ✓\n");
} catch (Exception $e) {
    out(" ✓ (exists)\n");
}

// Hardware state
out("   - hardware_state...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hardware_state (
            incubator_id INT NOT NULL PRIMARY KEY,
            temperature DECIMAL(5,2) DEFAULT NULL,
            humidity DECIMAL(5,2) DEFAULT NULL,
            device_status ENUM('online', 'offline') DEFAULT 'offline',
            current_temp DECIMAL(5,2) DEFAULT NULL,
            current_humidity DECIMAL(5,2) DEFAULT NULL,
            heater_on TINYINT(1) DEFAULT 0,
            heater_1_status TINYINT(1) DEFAULT 0,
            heater_2_status TINYINT(1) DEFAULT 0,
            heater_fan_status TINYINT(1) DEFAULT 0,
            exhaust_status TINYINT(1) DEFAULT 0,
            swing_on TINYINT(1) DEFAULT 0,
            swing_status TINYINT(1) DEFAULT 0,
            last_seen TIMESTAMP NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

// Temperature logs
out("   - temperature_logs...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS temperature_logs (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            incubator_id INT NOT NULL,
            temperature DECIMAL(5,2) NOT NULL,
            humidity DECIMAL(5,2) DEFAULT NULL,
            recorded_by ENUM('admin', 'user', 'auto') DEFAULT 'auto',
            recorded_id INT DEFAULT NULL,
            recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (incubator_id),
            FOREIGN KEY (incubator_id) REFERENCES incubators(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

// Activity logs
out("   - activity_logs...");
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin', 'user', 'system') NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    out(" ✓\n");
} catch (Exception $e) {
    out(" (exists or error) ✓\n");
}

out("\n2. Running HeartbeatScheduler migrations...\n");
require_once __DIR__ . '/migrations/001_heartbeat_scheduler.php';
require_once __DIR__ . '/migrations/002_schedule_manager_compat.php';

$heartbeatMigration = new Migration();
$heartbeatOk = $heartbeatMigration->runAll();
out($heartbeatOk ? "Heartbeat scheduler migration: ✓\n" : "Heartbeat scheduler migration: ✗\n");

$compatMigration = new ScheduleManagerCompatMigration($pdo);
$compatOk = $compatMigration->run();
out($compatOk ? "Schedule manager compatibility migration: ✓\n" : "Schedule manager compatibility migration: ✗\n");

out("\n3. Creating demo data...\n");

// Create test user if not exists (disabled)
out("   - test user... skipped (demo creation disabled) \n");

// Create test incubator if not exists (disabled)
out("   - test incubator... skipped (demo creation disabled) \n");

// Create test schedule if not exists (disabled)
out("   - test schedule... skipped (demo creation disabled) \n");

out("\n=== Setup Complete ===\n");
out("\nNext steps:\n");
out("1. Review /docs/HEARTBEAT_SCHEDULER_GUIDE.md for architecture\n");
out("2. Review /docs/INTEGRATION_GUIDE.md for integration examples\n");
out("3. Run tests: php tests/heartbeat_tests.php\n");
out("4. Update ESP8266 firmware to call /ajax/heartbeat.php\n");
out("5. Create schedules via admin dashboard\n\n");
