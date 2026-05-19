<?php
/**
 * ============================================================
 *  GHOST Heartbeat Scheduler — Simplified Test Suite
 *  File: /GHOST/tests/heartbeat_simple_tests.php
 *
 *  Simple test suite that works with or without complete schema
 *
 *  Run: php tests/heartbeat_simple_tests.php
 * ============================================================
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/HeartbeatScheduler.php';

class SimpleHeartbeatTests {
    private $pdo;
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        $this->pdo = getDB();
        // Disable foreign key checks for testing
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    }

    public function runAll() {
        echo "\n=== GHOST Heartbeat Scheduler - Simplified Tests ===\n\n";

        $this->setup();

        // Test 1: Basic heartbeat with no data
        $this->test1_SchemaCreation();

        // Test 2: HeartbeatScheduler initialization
        $this->test2_SchedulerInit();

        // Test 3: Session creation logic
        $this->test3_SessionCreation();

        // Test 4: Control state management  
        $this->test4_ControlsState();

        // Test 5: Safety threshold logic
        $this->test5_SafetyLogic();

        // Test 6: Session logger
        $this->test6_SessionLogger();

        $this->cleanup();

        echo "\n=== Test Results ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n\n";

        return $this->failed === 0;
    }

    private function setup() {
        // Ensure tables exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS test_scheduler_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_name VARCHAR(255),
                result VARCHAR(50),
                message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function cleanup() {
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    }

    private function passTest($name, $message = "") {
        echo "  ✓ PASSED: {$name}\n";
        if ($message) echo "           {$message}\n";
        $this->passed++;
    }

    private function failTest($name, $message = "") {
        echo "  ✗ FAILED: {$name}\n";
        if ($message) echo "           {$message}\n";
        $this->failed++;
    }

    /**
     * Test 1: Schema creation works
     */
    private function test1_SchemaCreation() {
        echo "Test 1: Schema creation and table verification\n";

        try {
            // Check sessions table
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'sessions'");
            if ($stmt->rowCount() > 0) {
                $this->passTest("Sessions table exists");
            } else {
                $this->failTest("Sessions table missing");
                return;
            }

            // Check controls_state table
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'controls_state'");
            if ($stmt->rowCount() > 0) {
                $this->passTest("Controls state table exists");
            } else {
                $this->failTest("Controls state table missing");
                return;
            }

            // Check session_logs table
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'session_logs'");
            if ($stmt->rowCount() > 0) {
                $this->passTest("Session logs table exists");
            } else {
                $this->failTest("Session logs table missing");
                return;
            }

            // Verify key columns exist in sessions
            $columns = [];
            $stmt = $this->pdo->query("SHOW COLUMNS FROM sessions");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $columns[$col['Field']] = true;
            }

            $required = ['id', 'incubator_id', 'status', 'target_temp', 'duration_hours', 'started_at'];
            $missing = array_diff($required, array_keys($columns));

            if (empty($missing)) {
                $this->passTest("Sessions table has all required columns");
            } else {
                $this->failTest("Sessions table missing columns", json_encode($missing));
            }

        } catch (Exception $e) {
            $this->failTest("Schema check", $e->getMessage());
        }
    }

    /**
     * Test 2: HeartbeatScheduler class can be instantiated
     */
    private function test2_SchedulerInit() {
        echo "Test 2: HeartbeatScheduler class initialization\n";

        try {
            $scheduler = new HeartbeatScheduler($this->pdo, 1, 37.5, 60.0);
            $this->passTest("HeartbeatScheduler instantiated");

            // Verify the class has required methods
            $methods = ['handleHeartbeat', 'checkSafetyLimits', 'applyCooldownLogic'];
            foreach ($methods as $method) {
                if (method_exists($scheduler, $method)) {
                    $this->passTest("Method exists: $method");
                } else {
                    $this->failTest("Missing method: $method");
                }
            }

        } catch (Exception $e) {
            $this->failTest("HeartbeatScheduler init", $e->getMessage());
        }
    }

    /**
     * Test 3: Session creation logic
     */
    private function test3_SessionCreation() {
        echo "Test 3: Session record creation\n";

        try {
            // Disable FK temporarily
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            // Insert a test session
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours, auto_started)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            $result = $stmt->execute([1, 'running', 37.5, 60.0, 24, 1]);

            if ($result) {
                $id = $this->pdo->lastInsertId();
                
                // Verify it was inserted
                $stmt = $this->pdo->prepare("SELECT * FROM sessions WHERE id = ?");
                $stmt->execute([$id]);
                $session = $stmt->fetch();

                if ($session && $session['status'] === 'running') {
                    $this->passTest("Session creation", "Session ID: {$id}");
                } else {
                    $this->failTest("Session retrieval");
                }
            } else {
                $this->failTest("Session insertion");
            }

        } catch (Exception $e) {
            $this->failTest("Session creation", $e->getMessage());
        }
    }

    /**
     * Test 4: Controls state management
     */
    private function test4_ControlsState() {
        echo "Test 4: Controls state management\n";

        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            // Insert controls state
            $stmt = $this->pdo->prepare(
                "INSERT INTO controls_state 
                 (incubator_id, status, target_temp, target_humidity, relay_heater)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE 
                   status = VALUES(status), 
                   target_temp = VALUES(target_temp)"
            );

            $result = $stmt->execute([2, 'running', 37.5, 60.0, 1]);

            if ($result) {
                // Retrieve it
                $stmt = $this->pdo->prepare("SELECT * FROM controls_state WHERE incubator_id = 2");
                $stmt->execute();
                $control = $stmt->fetch();

                if ($control && $control['status'] === 'running' && $control['relay_heater'] == 1) {
                    $this->passTest("Controls state management");
                } else {
                    $this->failTest("Controls state retrieval");
                }
            } else {
                $this->failTest("Controls state insertion");
            }

        } catch (Exception $e) {
            $this->failTest("Controls state", $e->getMessage());
        }
    }

    /**
     * Test 5: Safety threshold logic
     */
    private function test5_SafetyLogic() {
        echo "Test 5: Safety threshold detection\n";

        try {
            $scheduler = new HeartbeatScheduler($this->pdo, 1, 40.5, 60.0);  // 3°C over target

            // Create a mock session and control for testing
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours, auto_started)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([3, 'running', 37.5, 60.0, 24, 0]);

            // Test the safety check reflection
            $reflectionMethod = new ReflectionMethod('HeartbeatScheduler', 'checkSafetyLimits');
            $reflectionMethod->setAccessible(true);

            $control = [
                'target_temp' => 37.5,
                'relay_heater' => 1
            ];

            $safety = $reflectionMethod->invoke($scheduler, $control, ['target_temp' => 37.5]);

            if ($safety['triggered'] && $safety['level'] === 'critical') {
                $this->passTest("Safety detection", "Correctly detected critical overheat");
            } else {
                $this->failTest("Safety detection", "Expected critical level, got: " . json_encode($safety));
            }

        } catch (Exception $e) {
            $this->failTest("Safety logic", $e->getMessage());
        }
    }

    /**
     * Test 6: SessionLogger functionality
     */
    private function test6_SessionLogger() {
        echo "Test 6: Session event logging\n";

        try {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS=0");

            $logger = new SessionLogger($this->pdo);

            // Create a session first
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([4, 'running', 37.5, 60.0, 24]);
            $session_id = $this->pdo->lastInsertId();

            // Log an event
            $logger->logSessionEvent(
                $session_id,
                4,
                'session_start',
                37.0,
                60.0,
                'Test session started'
            );

            // Verify the log was created
            $stmt = $this->pdo->prepare(
                "SELECT * FROM session_logs WHERE session_id = ? ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->execute([$session_id]);
            $log = $stmt->fetch();

            if ($log && $log['event_type'] === 'session_start' && $log['temperature'] == 37.0) {
                $this->passTest("Session logging", "Event logged successfully");
            } else {
                $this->failTest("Session logging");
            }

        } catch (Exception $e) {
            $this->failTest("Session logging", $e->getMessage());
        }
    }
}

// Run tests
$tests = new SimpleHeartbeatTests();
$success = $tests->runAll();

exit($success ? 0 : 1);
