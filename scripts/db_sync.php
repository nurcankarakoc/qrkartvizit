<?php
require_once 'core/db.php';

try {
    // 1. Add draft_path to orders if missing
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS draft_path VARCHAR(255) DEFAULT NULL;");
    
    // 2. Create design_drafts table
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

    echo "Database sync complete successfully.\n";
} catch (Exception $e) {
    echo "Error syncing database: " . $e->getMessage();
}
