<?php

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isAdminLoggedIn() && !isUserLoggedIn()) {
    http_response_code(401);
    jsonResponse(array(
        'success' => false,
        'status' => 'unauthorized',
        'message' => 'Authentication required'
    ));
}

session_write_close();
ignore_user_abort(true);
@set_time_limit(20);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$source = isset($input['source']) ? trim((string)$input['source']) : 'authenticated-poll';

try {
    $worker = new SchedulerWorker(getDB());
    $result = $worker->run($source, array(
        'user_id' => isUserLoggedIn() ? (int)($_SESSION['user_id'] ?? 0) : null,
        'admin_id' => isAdminLoggedIn() ? (int)($_SESSION['admin_id'] ?? 0) : null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null
    ));

    jsonResponse($result);
} catch (Throwable $e) {
    http_response_code(500);
    jsonResponse(array(
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ));
}