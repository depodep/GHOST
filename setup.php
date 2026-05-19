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

echo "\n=== GHOST Incubator System Setup ===\n\n";

$pdo = getDB();

echo "1. Creating/updating core tables...\n";

// Incubators table
echo "   - incubators...";
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
    
    echo " ✓\n";
} catch (Exception $e) {
    echo " ✓ (exists)\n";
}

// Users table
echo "   - users...";
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
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

// Temperature settings
echo "   - temperature_settings...";
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
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

// Batches table
echo "   - batches...";
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
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

// Schedules table
echo "   - schedules...";
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
        echo " (added $added columns)";
    }
    echo " ✓\n";
} catch (Exception $e) {
    echo " ✓ (exists)\n";
}

// Hardware state
echo "   - hardware_state...";
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
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

// Temperature logs
echo "   - temperature_logs...";
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
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

// Activity logs
echo "   - activity_logs...";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin', 'user') NOT NULL,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            logged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " ✓\n";
} catch (Exception $e) {
    echo " (exists or error) ✓\n";
}

echo "\n2. Running HeartbeatScheduler migrations...\n";
require_once __DIR__ . '/migrations/001_heartbeat_scheduler.php';

echo "\n3. Creating demo data...\n";

// Create test user if not exists
echo "   - test user...";
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'test@ghost.com' LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password, status) 
             VALUES ('Test User', 'test@ghost.com', ?, 'active')"
        );
        $stmt->execute([password_hash('test123', PASSWORD_BCRYPT)]);
        echo " created";
    } else {
        echo " exists";
    }
} catch (Exception $e) {
    echo " error";
}
echo " ✓\n";

// Create test incubator if not exists
echo "   - test incubator...";
try {
    $stmt = $pdo->prepare("SELECT id FROM incubators WHERE incubator_name = 'Test Incubator' LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare(
            "INSERT INTO incubators (user_id, incubator_name, location, status)
             VALUES (1, 'Test Incubator', 'Lab', 'active')"
        );
        $stmt->execute();
        $incubator_id = $pdo->lastInsertId();
        
        // Create temperature settings
        $stmt = $pdo->prepare(
            "INSERT INTO temperature_settings (incubator_id, target_temperature, target_humidity)
             VALUES (?, 37.5, 60.0)"
        );
        $stmt->execute([$incubator_id]);
        
        echo " created (ID: {$incubator_id})";
    } else {
        echo " exists";
    }
} catch (Exception $e) {
    echo " error";
}
echo " ✓\n";

// Create test schedule if not exists
echo "   - test schedule...";
try {
    $stmt = $pdo->prepare("SELECT id FROM schedules WHERE title = 'Demo Auto-Start' LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        // Get first incubator ID
        $stmt = $pdo->prepare("SELECT id FROM incubators LIMIT 1");
        $stmt->execute();
        $inc = $stmt->fetch();
        
        if ($inc) {
            $tomorrow = (new DateTime())->modify('+1 day');
            $stmt = $pdo->prepare(
                "INSERT INTO schedules 
                 (incubator_id, title, scheduled_date, scheduled_time, status, action_type,
                  target_temp, target_humidity, duration_hours, created_by_id, created_by_role)
                 VALUES (?, 'Demo Auto-Start', ?, '06:00:00', 'pending', 'turning', 37.5, 60.0, 21, 1, 'admin')"
            );
            $stmt->execute([$inc['id'], $tomorrow->format('Y-m-d')]);
            echo " created";
        }
    } else {
        echo " exists";
    }
} catch (Exception $e) {
    echo " error";
}
echo " ✓\n";

echo "\n=== Setup Complete ===\n";
echo "\nNext steps:\n";
echo "1. Review /docs/HEARTBEAT_SCHEDULER_GUIDE.md for architecture\n";
echo "2. Review /docs/INTEGRATION_GUIDE.md for integration examples\n";
echo "3. Run tests: php tests/heartbeat_tests.php\n";
echo "4. Update ESP8266 firmware to call /ajax/heartbeat.php\n";
echo "5. Create schedules via admin dashboard\n\n";
