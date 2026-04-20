<?php
require_once 'core/db.php';
try {
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL, 
        ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL");
    echo "OK";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "OK (Columns already exist)";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
