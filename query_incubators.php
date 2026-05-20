<?php
$pdo = new PDO("mysql:host=localhost;dbname=ghost_incubator;charset=utf8", "root", "");
$stmt = $pdo->query("DESCRIBE incubators");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>