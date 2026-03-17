<?php
require_once __DIR__ . '/../core/db.php';

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

try {
    // 0.0 Ensure users table compatibility columns
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS kvkk_approved TINYINT(1) DEFAULT 0;");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL;");

    // 0.1 Ensure packages table has expected compatibility columns
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS has_physical_print TINYINT(1) DEFAULT 0;");
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS has_digital_profile TINYINT(1) DEFAULT 0;");
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS has_qr_code TINYINT(1) DEFAULT 0;");
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;");

    // 0. Ensure base tables exist (safe no-op if already created)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `order_id` INT DEFAULT NULL,
      `amount` DECIMAL(10, 2) NOT NULL,
      `type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order',
      `payment_type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 1. Orders compatibility columns
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS package VARCHAR(50) DEFAULT 'smart';");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS revision_count INT DEFAULT 2;");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS draft_path VARCHAR(255) DEFAULT NULL;");
    $pdo->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','pending_payment','pending_design','designing','awaiting_approval','revision_requested','approved','printing','shipping','completed','disputed') DEFAULT 'pending';");

    // 1.1 Map package_id -> package slug when possible
    if (column_exists($pdo, 'orders', 'package_id')) {
        $pdo->exec("UPDATE orders o
                    LEFT JOIN packages p ON p.id = o.package_id
                    SET o.package = COALESCE(o.package, p.slug, 'smart')
                    WHERE o.package IS NULL OR o.package = '';");
    } else {
        $pdo->exec("UPDATE orders SET package = COALESCE(package, 'smart') WHERE package IS NULL OR package = '';");
    }

    // 1.2 Ensure existing statuses are compatible with app flow
    $pdo->exec("UPDATE orders SET status = 'pending' WHERE status IN ('pending_design', 'pending_payment') OR status IS NULL OR status = '';");

    // 2. Payments compatibility column
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS type ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order';");
    if (column_exists($pdo, 'payments', 'payment_type')) {
        $pdo->exec("UPDATE payments SET type = payment_type WHERE type IS NULL;");
    }
    
    // 3. Create design_drafts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `design_drafts` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `order_id` INT NOT NULL,
      `designer_id` INT NOT NULL,
      `file_path` VARCHAR(255) NOT NULL,
      `customer_feedback` TEXT DEFAULT NULL,
      `status` ENUM('pending', 'approved', 'revision_requested', 'rejected') DEFAULT 'pending',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Database compatibility sync completed successfully.\n";
} catch (Exception $e) {
    echo "Error syncing database: " . $e->getMessage();
}
