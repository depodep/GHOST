<?php
// GHOST Incubator System - Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ghost_incubator');
define('SITE_NAME', 'GHOST Incubator');
define('SITE_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/GHOST');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PDO Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                )
            );
        } catch (PDOException $e) {
            die(json_encode(array('success' => false, 'message' => 'Database connection failed: ' . $e->getMessage())));
        }
    }
    return $pdo;
}

// Auth helpers
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit();
    }
}

function requireUser() {
    if (!isUserLoggedIn()) {
        header('Location: ' . BASE_URL . '/user/login.php');
        exit();
    }
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) return null;
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute(array($_SESSION['admin_id']));
    return $stmt->fetch();
}

function getCurrentUser() {
    if (!isUserLoggedIn()) return null;
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch();
}

function logActivity($role, $userId, $action, $details = '') {
    try {
        $pdo  = getDB();
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (role, user_id, action, details, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute(array($role, $userId, $action, $details, $ip));
    } catch (Exception $e) {}
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// PHP 7 compatible helpers replacing PHP 8 match() expressions
function actionIcon($type, $withLabel = false) {
    $map = array(
        'turning'           => array('&#x1F504;', 'Turning'),
        'temperature_check' => array('&#x1F321;&#xFE0F;', 'Temp Check'),
        'humidity_check'    => array('&#x1F4A7;', 'Humidity'),
        'candling'          => array('&#x1F56F;&#xFE0F;', 'Candling'),
        'hatch'             => array('&#x1F423;', 'Hatch'),
        'maintenance'       => array('&#x2699;&#xFE0F;', 'Maintenance'),
    );
    $icon  = isset($map[$type]) ? $map[$type][0] : '&#x2699;&#xFE0F;';
    $label = isset($map[$type]) ? $map[$type][1] : htmlspecialchars($type);
    return $withLabel ? $icon . ' ' . $label : $icon;
}

function severityIcon($severity) {
    if ($severity === 'warning') return '&#x26A0;&#xFE0F;';
    if ($severity === 'danger')  return '&#x1F6A8;';
    return '&#x2139;&#xFE0F;';
}
