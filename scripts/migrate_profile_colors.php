<?php
require_once __DIR__ . '/../core/db.php';

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "Added: {$column}\n";
    } else {
        echo "Already exists: {$column}\n";
    }
}

add_column_if_missing($pdo, 'profiles', 'brand_color',  "varchar(7) NULL DEFAULT NULL COMMENT 'Kişisel marka rengi, örn: #1a2b3c'");
add_column_if_missing($pdo, 'profiles', 'cover_photo',  "varchar(255) NULL DEFAULT NULL COMMENT 'Kapak / banner foto yolu'");

echo "Done.\n";
