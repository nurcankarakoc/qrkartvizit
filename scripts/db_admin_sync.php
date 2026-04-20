<?php
require_once __DIR__ . '/../core/db.php';

try {
    // 1. Packages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `packages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `display_label` VARCHAR(100) DEFAULT NULL,
      `short_label` VARCHAR(60) DEFAULT NULL,
      `slug` VARCHAR(50) NOT NULL UNIQUE,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Keep package pricing and package card texts aligned with the current product model
    $pdo->exec("INSERT INTO `packages` (
      `name`, `display_label`, `short_label`, `slug`, `price`, `has_physical_print`, `has_digital_profile`, `has_qr_code`,
      `included_revisions`, `description_text`, `included_features_json`, `excluded_features_json`, `register_title`,
      `register_subtitle`, `register_badge`, `register_price_text`, `register_features_json`, `register_note`, `register_panel_text`
    ) VALUES
    ('Klasik Paket', 'Klasik Paket', 'Klasik', 'classic', 299.00, 1, 0, 0, 2, 'Sadece baskılı kartvizit tasarımı ve baskı süreci sunar.', '[\"Baskılı kartvizit siparişi\",\"2 ücretsiz revize\",\"Tasarım takip ekranı\"]', '[\"Dijital profil linki\",\"QR paylaşımı\",\"Sosyal link modülü\"]', 'Klasik', 'Sadece baskı hizmeti, dijital panel yok', '', '799 ₺', '[\"Standart fiziksel kartvizit baskısı\",\"2 revize hakkı\",\"Kurumsal logo ve temel tasarım desteği\"]', 'Basılı kartvizit odaklı sade çözüm arayan müşteriler için uygundur.', 'Klasik pakette dijital panel bulunmaz. Bu nedenle aşağıdaki dijital panel alanları pasif durumdadır.'),
    ('Sadece Panel', 'Dijital Panel Paketi', 'Panel', 'panel', 700.00, 0, 1, 1, 0, 'Sadece dijital kartvizit, profil linki ve QR deneyimi sunar.', '[\"Dijital profil ve QR linki\",\"Sosyal link alanı\",\"Anında yayınlanabilir profil\"]', '[\"Baskılı kartvizit siparişi\",\"Basılı tasarım revizesi\"]', 'Sadece Panel', 'Sadece dijital kartvizit deneyimi', '', '700 ₺ / yıl', '[\"Fiziksel baskı olmadan tamamen dijital profil\",\"QR ile profil paylaşımı ve panel yönetimi\",\"Tek seferlik yıllık ödeme modeli\"]', 'Sadece dijital kartvizit isteyen müşteriler için yıllık erişim odaklı çözümdür.', 'Sadece Panel paketinde dijital kartvizit siteniz aktif olur. Bu ayarlar profilinizi doğrudan yayına hazırlar.'),
    ('Akıllı Paket', 'Akıllı Paket', 'Akıllı', 'smart', 1200.00, 1, 1, 1, 2, 'Dijital profil ile baskılı kartviziti bir arada sunar.', '[\"Dijital profil ve QR linki\",\"Baskılı kartvizit siparişi\",\"2 ücretsiz revize\",\"Sosyal link alanı\"]', '[]', '1000 Baskı + 1 Yıllık Erişim', 'Panel + 1000 baskı + dinamik QR', 'EN ÇOK TERCİH EDİLEN', '1.200 ₺ / yıl', '[\"1000 adet fiziksel kartvizit baskısı dahildir\",\"1 yıllık dijital panel erişimi tek ödeme ile aktif olur\",\"Dinamik QR ile bilgi güncelleme\",\"Tek seferlik yıllık ödeme modeli\"]', 'Dijital profil de isteyen müşteriler için en kapsamlı seçenek Akıllı pakettir.', 'Akıllı pakette dijital panel ve fiziksel baskı birlikte gelir. 1000 baskı dahildir ve 1 yıllık erişim tek ödeme ile tanımlanır.')
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
      `register_panel_text` = VALUES(`register_panel_text`);");

    // 2. Payments Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `order_id` INT DEFAULT NULL,
      `amount` DECIMAL(10, 2) NOT NULL,
      `type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order',
      `payment_type` ENUM('order', 'extra_revision', 'subscription_renewal') DEFAULT 'order',
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
