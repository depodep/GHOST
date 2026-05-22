<?php
/**
 * Remove demo data created by setup.php
 * Run: php scripts/remove_demo_data.php
 */

require_once __DIR__ . '/../includes/config.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Removing demo data...\n";

$pdo = getDB();
try {
    $pdo->beginTransaction();

    // Remove test user created by setup.php
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = :email");
    $stmt->execute(['email' => 'test@ghost.com']);
    $deletedUsers = $stmt->rowCount();
    echo "Deleted $deletedUsers test user(s)\n";

    // Find potential demo/test incubators by name/incubator_name/location
        $q = "SELECT id, name, incubator_name, location FROM incubators
            WHERE LOWER(name) LIKE :p1
             OR LOWER(incubator_name) LIKE :p2
             OR LOWER(location) LIKE :p3";
        $stmt = $pdo->prepare($q);
        $stmt->execute(['p1' => '%test%', 'p2' => '%test%', 'p3' => '%test%']);
    $incubators = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($incubators) === 0) {
        echo "No test incubators found (pattern '%test%').\n";
    } else {
        $deletedInc = 0;
        $delStmt = $pdo->prepare("DELETE FROM incubators WHERE id = :id");
        foreach ($incubators as $inc) {
            $delStmt->execute(['id' => $inc['id']]);
            $deletedInc += $delStmt->rowCount();
            echo "Deleted incubator ID={$inc['id']} name='{$inc['name']}' incubator_name='{$inc['incubator_name']}' location='{$inc['location']}'\n";
        }
        echo "Deleted $deletedInc incubator(s) and their related data (via cascade).\n";
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error removing demo data: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
