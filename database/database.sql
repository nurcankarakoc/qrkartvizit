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
  `reset_token` VARCHAR(64) DEFAULT NULL COMMENT 'Şifre sıfırlama token (bin2hex 32 byte)',
  `reset_expires` TIMESTAMP NULL DEFAULT NULL COMMENT 'Token geçerlilik süresi',
  INDEX (`email`),
  INDEX (`role`),
  INDEX (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Paket Tanımlamaları
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `display_label` VARCHAR(100) DEFAULT NULL,
  `short_label` VARCHAR(60) DEFAULT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE, -- 'classic', 'panel', 'smart'
  `price` DECIMAL(10, 2) NOT NULL,
  `has_physical_print` TINYINT(1) DEFAULT 0,
  `has_digital_profile` TINYINT(1) DEFAULT 0,
  `has_qr_code` TINYINT(1) DEFAULT 0,
  `included_revisions` INT DEFAULT 2,
  `description_text` TEXT DEFAULT NULL,
  `included_features_json` TEXT DEFAULT NULL,
  `excluded_features_json` TEXT DEFAULT NULL,
  `register_title` VARCHAR(150) DEFAULT NULL,
  `register_subtitle` VARCHAR(255) DEFAULT NULL,
  `register_badge` VARCHAR(120) DEFAULT NULL,
  `register_price_text` VARCHAR(120) DEFAULT NULL,
  `register_features_json` TEXT DEFAULT NULL,
  `register_note` TEXT DEFAULT NULL,
  `register_panel_text` TEXT DEFAULT NULL,
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
  `status` ENUM('pending', 'pending_payment', 'pending_design', 'designing', 'awaiting_approval', 'revision_requested', 'approved', 'printing', 'shipping', 'completed') DEFAULT 'pending',
  `revision_count` INT DEFAULT 2 COMMENT 'Legacy remaining revision rights',
  `current_revision_count` INT DEFAULT 0,
  `total_allowed_revisions` INT DEFAULT 2,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE SET NULL,
  INDEX (`status`),
  INDEX (`package`),
  INDEX (`user_id`),
  INDEX (`created_at`)
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
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
  INDEX (`profile_id`),
  INDEX (`sort_order`)
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
  `type` ENUM('order', 'subscription_renewal') DEFAULT 'order' COMMENT 'Legacy compatibility',
  `payment_type` ENUM('order', 'subscription_renewal') DEFAULT 'order',
  `status` ENUM('success', 'failed', 'refunded') DEFAULT 'success',
  `payment_details` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Sistem Logları (KVKK, Güvenlik ve Audit Trail)
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
INSERT INTO `packages` (
  `name`, `display_label`, `short_label`, `slug`, `price`, `has_physical_print`, `has_digital_profile`, `has_qr_code`,
  `included_revisions`, `description_text`, `included_features_json`, `excluded_features_json`, `register_title`,
  `register_subtitle`, `register_badge`, `register_price_text`, `register_features_json`, `register_note`,
  `register_panel_text`
) VALUES
('Klasik Paket', 'Klasik Paket', 'Klasik', 'classic', 299.00, 1, 0, 0, 2, 'Sadece baskılı kartvizit tasarımı ve baskı süreci sunar.', '["Baskılı kartvizit siparişi","2 ücretsiz revize","Tasarım takip ekranı"]', '["Dijital profil linki","QR paylaşımı","Sosyal link modülü"]', 'Klasik', 'Sadece baskı hizmeti, dijital panel yok', '', '799 ₺', '["Standart fiziksel kartvizit baskısı","2 revize hakkı","Kurumsal logo ve temel tasarım desteği"]', 'Basılı kartvizit odaklı sade çözüm arayan müşteriler için uygundur.', 'Klasik pakette dijital panel bulunmaz. Bu nedenle aşağıdaki dijital panel alanları pasif durumdadır.'),
('Sadece Panel', 'Dijital Panel Paketi', 'Panel', 'panel', 700.00, 0, 1, 1, 0, 'Sadece dijital kartvizit, profil linki ve QR deneyimi sunar.', '["Dijital profil ve QR linki","Sosyal link alanı","Anında yayınlanabilir profil"]', '["Baskılı kartvizit siparişi","Basılı tasarım revizesi"]', 'Sadece Panel', 'Sadece dijital kartvizit deneyimi', '', '700 ₺ / yıl', '["Fiziksel baskı olmadan tamamen dijital profil","QR ile profil paylaşımı ve panel yönetimi","Tek seferlik yıllık ödeme modeli"]', 'Sadece dijital kartvizit isteyen müşteriler için yıllık erişim odaklı çözümdür.', 'Sadece Panel paketinde dijital kartvizit siteniz aktif olur. Bu ayarlar profilinizi doğrudan yayına hazırlar.'),
('Akıllı Paket', 'Akıllı Paket', 'Akıllı', 'smart', 1200.00, 1, 1, 1, 2, 'Dijital profil ile baskılı kartviziti bir arada sunar.', '["Dijital profil ve QR linki","Baskılı kartvizit siparişi","2 ücretsiz revize","Sosyal link alanı"]', '[]', '1000 Baskı + 1 Yıllık Erişim', 'Panel + 1000 baskı + dinamik QR', 'EN ÇOK TERCİH EDİLEN', '1.200 ₺ / yıl', '["1000 adet fiziksel kartvizit baskısı dahildir","1 yıllık dijital panel erişimi tek ödeme ile aktif olur","Dinamik QR ile bilgi güncelleme","Tek seferlik yıllık ödeme modeli"]', 'Dijital profil de isteyen müşteriler için en kapsamlı seçenek Akıllı pakettir.', 'Akıllı pakette dijital panel ve fiziksel baskı birlikte gelir. 1000 baskı dahildir ve 1 yıllık erişim tek ödeme ile tanımlanır.')
ON DUPLICATE KEY UPDATE
`name` = VALUES(`name`),
`display_label` = VALUES(`display_label`),
`short_label` = VALUES(`short_label`),
`price` = VALUES(`price`),
`has_physical_print` = VALUES(`has_physical_print`),
`has_digital_profile` = VALUES(`has_digital_profile`),
`has_qr_code` = VALUES(`has_qr_code`),
`included_revisions` = VALUES(`included_revisions`),
`description_text` = VALUES(`description_text`),
`included_features_json` = VALUES(`included_features_json`),
`excluded_features_json` = VALUES(`excluded_features_json`),
`register_title` = VALUES(`register_title`),
`register_subtitle` = VALUES(`register_subtitle`),
`register_badge` = VALUES(`register_badge`),
`register_price_text` = VALUES(`register_price_text`),
`register_features_json` = VALUES(`register_features_json`),
`register_note` = VALUES(`register_note`),
`register_panel_text` = VALUES(`register_panel_text`);

-- 10. Dinamik Form Alanlari
CREATE TABLE IF NOT EXISTS `form_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_key` VARCHAR(80) NOT NULL UNIQUE,
  `field_label` VARCHAR(140) NOT NULL,
  `field_type` ENUM('text','textarea','select','email','url','tel','number') NOT NULL DEFAULT 'text',
  `placeholder` VARCHAR(255) DEFAULT NULL,
  `help_text` VARCHAR(255) DEFAULT NULL,
  `default_value` TEXT DEFAULT NULL,
  `show_on_packages` VARCHAR(120) DEFAULT NULL,
  `required_on_packages` VARCHAR(120) DEFAULT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `updated_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`sort_order`),
  INDEX (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Dinamik Alan Secenekleri
CREATE TABLE IF NOT EXISTS `form_field_options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_id` INT NOT NULL,
  `option_value` VARCHAR(120) NOT NULL,
  `option_label` VARCHAR(120) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_field_option` (`field_id`, `option_value`),
  INDEX `idx_field_active_sort` (`field_id`, `is_active`, `sort_order`),
  CONSTRAINT `fk_form_field_options_field`
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Tasarimci Talep Onaylari
CREATE TABLE IF NOT EXISTS `form_change_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_type` ENUM('field_create','option_create') NOT NULL,
  `field_id` INT DEFAULT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `requested_by` INT NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT DEFAULT NULL,
  `review_note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_form_change_status` (`status`, `created_at`),
  INDEX `idx_form_change_requested_by` (`requested_by`),
  CONSTRAINT `fk_form_change_field`
    FOREIGN KEY (`field_id`) REFERENCES `form_fields`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Siparis Form Cevaplari
CREATE TABLE IF NOT EXISTS `order_form_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `field_key` VARCHAR(80) NOT NULL,
  `field_label` VARCHAR(140) NOT NULL,
  `value_text` TEXT DEFAULT NULL,
  `value_source` ENUM('customer','default') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_order_field` (`order_id`, `field_key`),
  INDEX `idx_order_answers_order` (`order_id`),
  CONSTRAINT `fk_order_form_answers_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────
-- MIGRATION: Mevcut kurulumları güncellemek için ALTER komutları
-- Yeni kurulumda CREATE TABLE IF NOT EXISTS zaten yeterli.
-- ─────────────────────────────────────────────────────────────

-- users: reset_token / reset_expires ekle (yoksa)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `reset_token`   VARCHAR(64)       DEFAULT NULL COMMENT 'Şifre sıfırlama token',
  ADD COLUMN IF NOT EXISTS `reset_expires` TIMESTAMP         NULL DEFAULT NULL COMMENT 'Token geçerlilik süresi';

-- users: reset_token index (yoksa)
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_reset_token` (`reset_token`);

-- orders: user_id + created_at index (yoksa)
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_orders_user_id`   (`user_id`);
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_orders_created_at` (`created_at`);

-- social_links: profile_id + sort_order index (yoksa)
ALTER TABLE `social_links` ADD INDEX IF NOT EXISTS `idx_social_profile_id` (`profile_id`);
ALTER TABLE `social_links` ADD INDEX IF NOT EXISTS `idx_social_sort`       (`sort_order`);
