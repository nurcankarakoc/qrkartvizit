<?php
require_once __DIR__ . '/../core/db.php';
$stmt = $pdo->query('SELECT slug FROM profiles LIMIT 10');
foreach ($stmt->fetchAll() as $r) {
    echo $r['slug'] . PHP_EOL;
}
