<?php
require_once 'core/db.php';
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n" . implode("\n", $tables) . "\n\n";

if (in_array('users', $tables)) {
    $stmt = $pdo->query('DESCRIBE users');
    echo "USERS COLUMNS:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
