<?php
require_once 'core/db.php';

try {
    // 1. Packages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `packages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `slug` VARCHAR(50) NOT NULL UNIQUE,
      `price` DECIMAL(10, 2) NOT NULL,
      `included_revisions` INT DEFAULT 2
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check if packages exist, if not insert defaults
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO `packages` (`name`, `slug`, `price`, `included_revisions`) VALUES
        ('Klasik Paket', 'classic', 299.00, 2),
        ('Sadece Panel', 'panel', 199.00, 0),
        ('Akıllı Paket', 'smart', 499.00, 2);");
    }

    // 2. Payments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `order_id` INT DEFAULT NULL,
      `amount` DECIMAL(10, 2) NOT NULL,
      `type` ENUM('order', 'extra_revision') DEFAULT 'order',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Disputes Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `disputes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `order_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `reason` TEXT NOT NULL,
      `status` ENUM('pending', 'resolved_favor_customer', 'resolved_favor_designer', 'closed') DEFAULT 'pending',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Admin database sync complete.\n";
} catch (Exception $e) {
    echo "Error syncing database: " . $e->getMessage();
}
