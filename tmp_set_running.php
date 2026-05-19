<?php
require_once 'includes/config.php';
$pdo = getDB();
$inc=1;
$start='2026-05-19 09:25:21';
$ends='2026-06-09 09:25:21';
$batchName='Alpha Unit - 1 eggs';
$pdo->prepare("UPDATE hardware_state SET session_status='running', session_started_at=?, session_ends_at=?, active_session_name=?, current_mode='incubating', last_server_sync=NOW(), last_seen=NOW(), last_active_at=NOW() WHERE incubator_id=?")->execute([$start,$ends,$batchName,$inc]);
echo "Updated hardware_state\n";