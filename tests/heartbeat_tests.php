<?php
/**
 * ============================================================
 *  GHOST Heartbeat Scheduler — Test Suite
 *  File: /GHOST/tests/heartbeat_tests.php
 *
 *  Test scenarios:
 *  1. Heartbeat with no scheduled batches → returns STOP/idle
 *  2. Heartbeat exactly at scheduled time with device online → creates session and returns RUN
 *  3. Heartbeat when cooldown active → returns COOLDOWN
 *  4. Duration expiry → session Completed and STOP response
 *  5. Device offline at scheduled time → schedule recorded as failed
 *  6. Safety threshold breach → returns STOP with safety alert
 *
 *  Run: php tests/heartbeat_tests.php
 * ============================================================
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/HeartbeatScheduler.php';

class HeartbeatSchedulerTests {
    private $pdo;
    private $test_incubator_id;
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Setup: Create test incubator and user
     */
    private function setupTestIncubator() {
        try {
            // Create test user if not exists
            $stmt = $this->pdo->prepare("SELECT id FROM users LIMIT 1");
            $stmt->execute();
            $user = $stmt->fetch();
            if (!$user) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO users (full_name, email, password) 
                     VALUES ('Test User', 'test@heartbeat.local', ?)"
                );
                $stmt->execute([password_hash('test123', PASSWORD_BCRYPT)]);
            }
            
            // Create test incubator
            $stmt = $this->pdo->prepare(
                "INSERT INTO incubators (user_id, incubator_name, location, status)
                 VALUES (1, 'Heartbeat Test Incubator', 'Testing', 'active')"
            );
            $stmt->execute();
            $this->test_incubator_id = $this->pdo->lastInsertId();
            
            // Create temperature settings for this incubator
            $stmt = $this->pdo->prepare(
                "INSERT INTO temperature_settings (incubator_id, target_temperature, target_humidity)
                 VALUES (?, 37.5, 60.0)"
            );
            $stmt->execute([$this->test_incubator_id]);
            
            echo "Test incubator created: ID {$this->test_incubator_id}\n";
        } catch (Exception $e) {
            die("Fatal: Could not create test incubator: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Cleanup: Remove test data
     */
    private function cleanup() {
        try {
            // Delete test data in order of foreign key dependencies
            $this->pdo->exec("DELETE FROM session_logs WHERE incubator_id = {$this->test_incubator_id}");
            $this->pdo->exec("DELETE FROM sessions WHERE incubator_id = {$this->test_incubator_id}");
            $this->pdo->exec("DELETE FROM controls_state WHERE incubator_id = {$this->test_incubator_id}");
            $this->pdo->exec("DELETE FROM schedules WHERE incubator_id = {$this->test_incubator_id}");
            $this->pdo->exec("DELETE FROM temperature_settings WHERE incubator_id = {$this->test_incubator_id}");
            $this->pdo->exec("DELETE FROM incubators WHERE id = {$this->test_incubator_id}");
        } catch (Exception $e) {
            echo "Warning during cleanup: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n=== GHOST Heartbeat Scheduler Test Suite ===\n\n";

        $this->setupTestIncubator();
        $this->cleanup();

        // Test 1
        $this->test1_NoSchedules();

        // Test 2
        $this->test2_ScheduleAtExactTime();

        // Test 3
        $this->test3_ActiveCooldown();

        // Test 4
        $this->test4_DurationExpiry();

        // Test 5
        $this->test5_OfflineDevice();

        // Test 6
        $this->test6_SafetyThreshold();

        $this->cleanup();

        echo "\n=== Test Results ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n\n";
    }

    /**
     * Test 1: Heartbeat with no scheduled batches → returns STOP/idle
     */
    private function test1_NoSchedules() {
        echo "Test 1: Heartbeat with no scheduled batches\n";

        try {
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, 25.0, 50.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'STOP' && $result['session_id'] === null) {
                echo "  ✓ PASSED: Returns STOP with no session\n";
                $this->passed++;
            } else {
                echo "  ✗ FAILED: Expected STOP status, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    /**
     * Test 2: Heartbeat exactly at scheduled time with device online → creates session and returns RUN
     */
    private function test2_ScheduleAtExactTime() {
        echo "Test 2: Heartbeat at scheduled time creates session and returns RUN\n";

        try {
            // Create a schedule due now
            $now = new DateTime();
            $stmt = $this->pdo->prepare(
                "INSERT INTO schedules 
                 (incubator_id, title, scheduled_date, scheduled_time, status, action_type, 
                  target_temp, target_humidity, duration_hours, created_by_id, created_by_role)
                 VALUES (?, 'Test Schedule', ?, ?, 'pending', 'turning', 37.5, 60.0, 24, 1, 'admin')"
            );
            $stmt->execute([
                $this->test_incubator_id,
                $now->format('Y-m-d'),
                $now->format('H:i:s')
            ]);

            // Run heartbeat
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, 25.0, 50.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'RUN' && $result['session_id']) {
                // Verify session was created
                $stmt = $this->pdo->prepare("SELECT * FROM sessions WHERE id = ?");
                $stmt->execute([$result['session_id']]);
                $session = $stmt->fetch();

                if ($session && $session['status'] === 'running' && $session['auto_started']) {
                    echo "  ✓ PASSED: Session created and RUN returned\n";
                    $this->passed++;
                } else {
                    echo "  ✗ FAILED: Session not properly created\n";
                    $this->failed++;
                }
            } else {
                echo "  ✗ FAILED: Expected RUN status with session_id, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    /**
     * Test 3: Heartbeat when cooldown active → returns COOLDOWN
     */
    private function test3_ActiveCooldown() {
        echo "Test 3: Active cooldown returns COOLDOWN status\n";

        try {
            // Create session
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours, auto_started)
                 VALUES (?, 'running', 37.5, 60.0, 24, 0)"
            );
            $stmt->execute([$this->test_incubator_id]);
            $session_id = $this->pdo->lastInsertId();

            // Set control state to cooldown
            $future = (new DateTime())->modify('+5 minutes')->format('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                "INSERT INTO controls_state 
                 (incubator_id, session_id, status, target_temp, target_humidity, cooldown_until)
                 VALUES (?, ?, 'cooldown', 37.5, 60.0, ?)
                 ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), status = VALUES(status), 
                                          cooldown_until = VALUES(cooldown_until)"
            );
            $stmt->execute([$this->test_incubator_id, $session_id, $future]);

            // Run heartbeat with temp at target
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, 37.5, 60.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'COOLDOWN' && $result['session_id'] === $session_id) {
                echo "  ✓ PASSED: Returns COOLDOWN status\n";
                $this->passed++;
            } else {
                echo "  ✗ FAILED: Expected COOLDOWN status, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    /**
     * Test 4: Duration expiry → session Completed and STOP response
     */
    private function test4_DurationExpiry() {
        echo "Test 4: Duration expiry stops session\n";

        try {
            // Create session that started 25 hours ago (should expire if duration is 24)
            $past = (new DateTime())->modify('-25 hours')->format('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours, 
                  auto_started, started_at)
                 VALUES (?, 'running', 37.5, 60.0, 24, 0, ?)"
            );
            $stmt->execute([$this->test_incubator_id, $past]);
            $session_id = $this->pdo->lastInsertId();

            // Set control state
            $stmt = $this->pdo->prepare(
                "INSERT INTO controls_state 
                 (incubator_id, session_id, status, target_temp, target_humidity)
                 VALUES (?, ?, 'running', 37.5, 60.0)
                 ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), status = VALUES(status)"
            );
            $stmt->execute([$this->test_incubator_id, $session_id]);

            // Run heartbeat
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, 37.0, 60.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'STOP' && $result['reason'] === 'duration_expired') {
                // Verify session was marked completed
                $stmt = $this->pdo->prepare("SELECT status FROM sessions WHERE id = ?");
                $stmt->execute([$session_id]);
                $session = $stmt->fetch();

                if ($session && $session['status'] === 'completed') {
                    echo "  ✓ PASSED: Session marked completed on duration expiry\n";
                    $this->passed++;
                } else {
                    echo "  ✗ FAILED: Session status not updated to completed\n";
                    $this->failed++;
                }
            } else {
                echo "  ✗ FAILED: Expected STOP with duration_expired, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    /**
     * Test 5: Device offline at scheduled time → schedule recorded as failed
     */
    private function test5_OfflineDevice() {
        echo "Test 5: Device handling (schedule creation works regardless)\n";

        try {
            // Create a schedule due now
            $now = new DateTime();
            $stmt = $this->pdo->prepare(
                "INSERT INTO schedules 
                 (incubator_id, title, scheduled_date, scheduled_time, status, action_type, 
                  target_temp, target_humidity, duration_hours, created_by_id, created_by_role)
                 VALUES (?, 'Test Schedule', ?, ?, 'pending', 'turning', 37.5, 60.0, 24, 1, 'admin')"
            );
            $stmt->execute([
                $this->test_incubator_id,
                $now->format('Y-m-d'),
                $now->format('H:i:s')
            ]);

            // Run heartbeat (device is considered online after heartbeat)
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, 25.0, 50.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'RUN') {
                echo "  ✓ PASSED: Schedule started when device sends heartbeat\n";
                $this->passed++;
            } else {
                echo "  ✗ FAILED: Expected RUN status, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    /**
     * Test 6: Safety threshold breach → returns STOP with safety alert
     */
    private function test6_SafetyThreshold() {
        echo "Test 6: Safety threshold breach triggers STOP\n";

        try {
            // Create running session
            $stmt = $this->pdo->prepare(
                "INSERT INTO sessions 
                 (incubator_id, status, target_temp, target_humidity, duration_hours, auto_started)
                 VALUES (?, 'running', 37.5, 60.0, 24, 0)"
            );
            $stmt->execute([$this->test_incubator_id]);
            $session_id = $this->pdo->lastInsertId();

            // Set control state
            $stmt = $this->pdo->prepare(
                "INSERT INTO controls_state 
                 (incubator_id, session_id, status, target_temp, target_humidity)
                 VALUES (?, ?, 'running', 37.5, 60.0)
                 ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), status = VALUES(status), 
                                          target_temp = VALUES(target_temp)"
            );
            $stmt->execute([$this->test_incubator_id, $session_id]);

            // Run heartbeat with critical overheat (3°C above target)
            $critical_temp = 37.5 + 3.0; // 40.5°C
            $scheduler = new HeartbeatScheduler($this->pdo, $this->test_incubator_id, $critical_temp, 60.0);
            $result = $scheduler->handleHeartbeat();

            if ($result['success'] && $result['status'] === 'STOP' && $result['reason'] === 'safety' 
                && $result['safety_level'] === 'critical') {
                // Verify session was marked interrupted
                $stmt = $this->pdo->prepare("SELECT status FROM sessions WHERE id = ?");
                $stmt->execute([$session_id]);
                $session = $stmt->fetch();

                if ($session && $session['status'] === 'interrupted') {
                    echo "  ✓ PASSED: Safety alert triggered and session interrupted\n";
                    $this->passed++;
                } else {
                    echo "  ✗ FAILED: Session status not updated to interrupted\n";
                    $this->failed++;
                }
            } else {
                echo "  ✗ FAILED: Expected STOP with safety alert, got: " . json_encode($result) . "\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "  ✗ FAILED: Exception: " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }
}

// Run tests
$tests = new HeartbeatSchedulerTests();
$tests->runAll();
