-- Zerosoft QR Kartvizit Platformu - SQL Veritabanı Şeması
-- Hazırlayan: Antigravity AI (Expert Engineer)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Kullanıcılar (Müşteri, Tasarımcı, Admin)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `role` ENUM('customer', 'designer', 'admin') DEFAULT 'customer',
  `kvkk_approved` TINYINT(1) DEFAULT 0 COMMENT 'KVKK Aydınlatma Metni onayı',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  INDEX (`email`),
  INDEX (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Paket Tanımlamaları
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE, -- 'classic', 'panel', 'smart'
  `price` DECIMAL(10, 2) NOT NULL,
  `has_physical_print` TINYINT(1) DEFAULT 0,
  `has_digital_profile` TINYINT(1) DEFAULT 0,
  `has_qr_code` TINYINT(1) DEFAULT 0,
  `included_revisions` INT DEFAULT 2,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Siparişler
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `package` VARCHAR(50) DEFAULT 'smart' COMMENT 'Legacy slug: classic|panel|smart',
  `package_id` INT DEFAULT NULL COMMENT 'Normalized package reference (optional)',
  `company_name` VARCHAR(255) DEFAULT NULL,
  `job_title` VARCHAR(255) DEFAULT NULL,
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `draft_path` VARCHAR(255) DEFAULT NULL,
  `design_notes` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'pending_payment', 'pending_design', 'designing', 'awaiting_approval', 'revision_requested', 'approved', 'printing', 'shipping', 'completed', 'disputed') DEFAULT 'pending',
  `revision_count` INT DEFAULT 2 COMMENT 'Legacy remaining revision rights',
  `current_revision_count` INT DEFAULT 0,
  `total_allowed_revisions` INT DEFAULT 2,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE SET NULL,
  INDEX (`status`),
  INDEX (`package`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Dijital Profiller
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `order_id` INT DEFAULT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `full_name` VARCHAR(255) DEFAULT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `company` VARCHAR(255) DEFAULT NULL,
  `phone_work` VARCHAR(50) DEFAULT NULL,
  `phone_mobile` VARCHAR(50) DEFAULT NULL,
  `email_work` VARCHAR(255) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `qr_path` VARCHAR(255) DEFAULT NULL,
  `vcard_path` VARCHAR(255) DEFAULT NULL,
  `theme_color` VARCHAR(20) DEFAULT '#3498db',
  `is_active` TINYINT(1) DEFAULT 1,
  `view_count` INT DEFAULT 0,
  `expiry_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX (`slug`),
  INDEX (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Sosyal Medya Linkleri
CREATE TABLE IF NOT EXISTS `social_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT NOT NULL,
  `platform` VARCHAR(50) NOT NULL, -- 'instagram', 'linkedin', 'whatsapp', 'facebook', 'twitter', 'tiktok', 'youtube'
  `url` VARCHAR(500) NOT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tasarımlar ve Taslaklar (Versioning)
CREATE TABLE IF NOT EXISTS `design_drafts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `designer_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `customer_feedback` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'revision_requested', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`designer_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Ödeme Kayıtları
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `order_id` INT DEFAULT NULL,
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'TRY',
  `type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order' COMMENT 'Legacy compatibility',
  `payment_type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order',
  `status` ENUM('success', 'failed', 'refunded') DEFAULT 'success',
  `payment_details` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Uyuşmazlıklar (Disputes)
CREATE TABLE IF NOT EXISTS `disputes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `reason` TEXT NOT NULL,
  `admin_id` INT DEFAULT NULL,
  `admin_note` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'resolved_favor_customer', 'resolved_favor_designer', 'closed') DEFAULT 'pending',
  `resolution_date` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Sistem Logları (KVKK, Güvenlik ve Audit Trail)
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL, -- 'login', 'kvkk_consent', 'profile_update', 'payment_failed', 'security_alert'
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Varsayilan Paketleri Ekleme
INSERT INTO `packages` (`name`, `slug`, `price`, `included_revisions`) VALUES
('Klasik Paket', 'classic', 299.00, 2),
('Sadece Panel', 'panel', 700.00, 0),
('Akilli Paket', 'smart', 1200.00, 2)
ON DUPLICATE KEY UPDATE
`name` = VALUES(`name`),
`price` = VALUES(`price`),
`included_revisions` = VALUES(`included_revisions`);

SET FOREIGN_KEY_CHECKS = 1;
