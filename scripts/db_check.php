<?php
require_once 'core/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: " . implode(", ", $tables) . "\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table` ");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Table: $table, Columns: " . implode(", ", $columns) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
