<?php
$url = 'http://localhost/GHOST/ajax/hardware_api.php';
$data = http_build_query([
    'action' => 'start_session',
    'incubator_id' => 1,
    'egg_count' => 4,
    'session_duration_sec' => 86400,
    'token' => 'ghost_hw_secret_2024'
]);
$options = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 5
    ]
];
$context = stream_context_create($options);
$res = @file_get_contents($url, false, $context);
file_put_contents('tmp_post_response.txt', $res === false ? "" : $res);
echo "Posted to API, response saved to tmp_post_response.txt\n";