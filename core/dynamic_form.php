<?php

declare(strict_types=1);

if (!function_exists('df_table_exists')) {
    function df_table_exists(PDO $pdo, string $table): bool
    {
        $table_escaped = str_replace("'", "''", $table);
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('df_table_has_column')) {
    function df_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        if (!df_table_exists($pdo, $table)) {
            return false;
        }

        $table_escaped = str_replace('`', '``', $table);
        $column_escaped = str_replace("'", "''", $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('df_ensure_dynamic_form_schema')) {
    function df_ensure_dynamic_form_schema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `form_fields` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `form_field_options` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `form_change_requests` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `order_form_answers` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('df_seed_default_form_fields')) {
    function df_seed_default_form_fields(PDO $pdo): void
    {
        df_ensure_dynamic_form_schema($pdo);

        $defaults = [
            [
                'field_key' => 'full_name',
                'field_label' => 'Ad Soyad',
                'field_type' => 'text',
                'placeholder' => 'Örn: Ahmet Kaya',
                'help_text' => null,
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 10,
            ],
            [
                'field_key' => 'company_name',
                'field_label' => 'Şirket Adı',
                'field_type' => 'text',
                'placeholder' => 'Örn: Zerosoft Teknoloji',
                'help_text' => null,
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 20,
            ],
            [
                'field_key' => 'job_title',
                'field_label' => 'Mesleki Unvan',
                'field_type' => 'text',
                'placeholder' => 'Örn: Satış Danışmanı',
                'help_text' => null,
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 30,
            ],
            [
                'field_key' => 'material_type',
                'field_label' => 'Malzeme Türü',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Baskılı siparişlerde uygulanacak malzeme tipi.',
                'default_value' => 'Karton',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 40,
            ],
            [
                'field_key' => 'design_notes',
                'field_label' => 'Genel Tasarım Notları',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Lacivert zemin, sade tipografi, minimal bir çizgi...',
                'help_text' => null,
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 50,
            ],
            [
                'field_key' => 'print_requirements',
                'field_label' => 'Baskı Talepleri',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Mat selefon, 350gr, altın yaldız...',
                'help_text' => 'Baskılı siparişlerde bu alan zorunludur.',
                'default_value' => 'Standart baskı uygulanacak.',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 60,
            ],
        ];

        $sql = "INSERT INTO form_fields
                (field_key, field_label, field_type, placeholder, help_text, default_value, show_on_packages, required_on_packages, is_required, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    field_label = VALUES(field_label),
                    field_type = VALUES(field_type),
                    placeholder = VALUES(placeholder),
                    help_text = VALUES(help_text),
                    default_value = VALUES(default_value),
                    show_on_packages = VALUES(show_on_packages),
                    required_on_packages = VALUES(required_on_packages),
                    is_required = VALUES(is_required),
                    sort_order = VALUES(sort_order)";

        $stmt = $pdo->prepare($sql);
        foreach ($defaults as $field) {
            $stmt->execute([
                $field['field_key'],
                $field['field_label'],
                $field['field_type'],
                $field['placeholder'],
                $field['help_text'],
                $field['default_value'],
                $field['show_on_packages'],
                $field['required_on_packages'],
                $field['is_required'],
                $field['is_active'],
                $field['sort_order'],
            ]);
        }

        $field_id_stmt = $pdo->prepare('SELECT id FROM form_fields WHERE field_key = ? LIMIT 1');
        $field_id_stmt->execute(['material_type']);
        $material_field_id = (int) ($field_id_stmt->fetchColumn() ?: 0);
        if ($material_field_id <= 0) {
            return;
        }

        $material_options = [
            ['value' => 'Karton', 'label' => 'Karton', 'sort_order' => 10],
            ['value' => 'Kuse_350gr', 'label' => 'Kuse 350gr', 'sort_order' => 20],
            ['value' => 'Mat_Selefon', 'label' => 'Mat Selefon', 'sort_order' => 30],
        ];

        $opt_stmt = $pdo->prepare(
            "INSERT INTO form_field_options (field_id, option_value, option_label, sort_order, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE option_label = VALUES(option_label), sort_order = VALUES(sort_order)"
        );

        foreach ($material_options as $option) {
            $opt_stmt->execute([
                $material_field_id,
                $option['value'],
                $option['label'],
                $option['sort_order'],
            ]);
        }
    }
}

if (!function_exists('df_seed_print_brief_fields')) {
    function df_seed_print_brief_fields(PDO $pdo): void
    {
        df_ensure_dynamic_form_schema($pdo);

        $fields = [
            [
                'field_key' => 'full_name',
                'field_label' => 'Ad Soyad',
                'field_type' => 'text',
                'placeholder' => 'Örn: Ahmet Kaya',
                'help_text' => 'Kartvizitte görünecek isim.',
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => 'classic,smart,panel',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 10,
            ],
            [
                'field_key' => 'company_name',
                'field_label' => 'Şirket / Marka Adı',
                'field_type' => 'text',
                'placeholder' => 'Örn: Zerosoft Teknoloji',
                'help_text' => 'Kartvizitte yer alacak şirket veya marka adı.',
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 20,
            ],
            [
                'field_key' => 'job_title',
                'field_label' => 'Mesleki Unvan',
                'field_type' => 'text',
                'placeholder' => 'Örn: Satış Danışmanı',
                'help_text' => 'Kartvizitte görünecek görev veya uzmanlık unvanı.',
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 30,
            ],
            // print_quantity kaldırıldı — müşteri zaten paketi kapsamındaki adedi sipariş eder,
            // ayrıca "kaç adet" sormanın anlamı yok.
            [
                'field_key' => 'card_size',
                'field_label' => 'Kart Ölçüsü',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Standart ölçü dışına çıkılacaksa burada mutlaka belirtin.',
                'default_value' => '85x55_mm',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 50,
            ],
            [
                'field_key' => 'card_orientation',
                'field_label' => 'Yerleşim Yönü',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Kartın yatay mı dikey mi tasarlanacağını seçin.',
                'default_value' => 'yatay',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 60,
            ],
            [
                'field_key' => 'print_sides',
                'field_label' => 'Baskı Yüzü',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Tek yön veya çift yön baskı tercihini netleştirin.',
                'default_value' => 'cift_yon',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 70,
            ],
            [
                'field_key' => 'material_type',
                'field_label' => 'Kağıt / Malzeme',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Baskıcıya uygun kağıt ve gramaj bilgisini seçin.',
                'default_value' => 'mat_kuse_350gr',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 80,
            ],
            [
                'field_key' => 'lamination_finish',
                'field_label' => 'Yüzey / Selefon',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Mat, parlak veya dokulu yüzey tercihlerini netleştirin.',
                'default_value' => 'mat_selefon',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 90,
            ],
            [
                'field_key' => 'design_style',
                'field_label' => 'Tasarım Stili',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Tasarım ekibinin görsel dili doğru kurması için ana stili seçin.',
                'default_value' => 'kurumsal_minimal',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 100,
            ],
            [
                'field_key' => 'color_preferences',
                'field_label' => 'Renk / Kurumsal Renk Bilgisi',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Listedeki ana rengi seçin. Tasarım ekibi bu tonu baz alır.',
                'default_value' => '#0F2747',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 110,
            ],
            [
                'field_key' => 'card_content_brief',
                'field_label' => 'Kartta Yer Alacak Bilgiler',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Ön yüzde ad soyad, unvan, telefon, e-posta, web sitesi. Arka yüzde QR ve kısa slogan.',
                'help_text' => 'Kart üzerinde görünmesini istediğiniz tüm bilgi satırlarını net sırayla yazın.',
                'default_value' => '',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => 'classic,smart',
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 120,
            ],
            [
                'field_key' => 'back_side_brief',
                'field_label' => 'Arka Yüz İçeriği',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Sadece logo ve QR olsun / boş kalsın / hizmet listesi yer alsın.',
                'help_text' => 'Tek yön baskı istiyorsanız boş bırakabilirsiniz.',
                'default_value' => '',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 130,
            ],
            [
                'field_key' => 'special_finish',
                'field_label' => 'Özel Uygulama',
                'field_type' => 'select',
                'placeholder' => null,
                'help_text' => 'Yaldız, kısmi lak, kabartma gibi ekstra uygulamalar varsa belirtin.',
                'default_value' => 'yok',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 140,
            ],
            [
                'field_key' => 'reference_examples',
                'field_label' => 'Referans / Beğendiğiniz Örnekler',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Minimal, bol boşluklu, premium hissi veren; siyah zemin istemiyorum.',
                'help_text' => 'Beğendiğiniz veya istemediğiniz tasarım yönlerini burada belirtin.',
                'default_value' => '',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 0,
                'sort_order' => 150,
            ],
            [
                'field_key' => 'design_notes',
                'field_label' => 'Genel Tasarım Notları',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Daha premium, sakin, okunaklı ve güven veren bir görünüm istiyorum.',
                'help_text' => 'Genel beklenti, ton ve tasarım yönünü serbest biçimde yazabilirsiniz.',
                'default_value' => '',
                'show_on_packages' => null,
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 160,
            ],
            [
                'field_key' => 'print_requirements',
                'field_label' => 'Ek Baskı Notları',
                'field_type' => 'textarea',
                'placeholder' => 'Örn: Renkler kurumsal kılavuza birebir uysun, fontlar baskıda kalınlaşmasın, QR alanı rahat taransın.',
                'help_text' => 'Yukarıdaki alanlara eklemek istediğiniz baskı notları varsa buraya yazın.',
                'default_value' => '',
                'show_on_packages' => 'classic,smart',
                'required_on_packages' => null,
                'is_required' => 0,
                'is_active' => 1,
                'sort_order' => 170,
            ],
        ];

        $field_stmt = $pdo->prepare(
            "INSERT INTO form_fields
             (field_key, field_label, field_type, placeholder, help_text, default_value, show_on_packages, required_on_packages, is_required, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                field_label = VALUES(field_label),
                field_type = VALUES(field_type),
                placeholder = VALUES(placeholder),
                help_text = VALUES(help_text),
                default_value = VALUES(default_value),
                show_on_packages = VALUES(show_on_packages),
                required_on_packages = VALUES(required_on_packages),
                is_required = VALUES(is_required),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order)"
        );

        foreach ($fields as $field) {
            $field_stmt->execute([
                $field['field_key'],
                $field['field_label'],
                $field['field_type'],
                $field['placeholder'],
                $field['help_text'],
                $field['default_value'],
                $field['show_on_packages'],
                $field['required_on_packages'],
                $field['is_required'],
                $field['is_active'],
                $field['sort_order'],
            ]);
        }

        // Kaldırılan alanları veritabanında pasif yap
        $pdo->prepare("UPDATE form_fields SET is_active = 0 WHERE field_key = 'print_quantity'")->execute();
        $pdo->prepare("UPDATE form_fields SET is_active = 0 WHERE field_key = 'reference_examples'")->execute();

        $field_id_stmt = $pdo->prepare('SELECT id FROM form_fields WHERE field_key = ? LIMIT 1');
        $deactivate_option_stmt = $pdo->prepare('UPDATE form_field_options SET is_active = 0 WHERE field_id = ?');
        $option_stmt = $pdo->prepare(
            "INSERT INTO form_field_options (field_id, option_value, option_label, sort_order, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                option_label = VALUES(option_label),
                sort_order = VALUES(sort_order),
                is_active = VALUES(is_active)"
        );

        $option_map = [
            'card_size' => [
                ['value' => '85x55_mm', 'label' => 'Standart 85 x 55 mm', 'sort_order' => 10],
                ['value' => '90x50_mm', 'label' => '90 x 50 mm', 'sort_order' => 20],
                ['value' => 'kare_65x65_mm', 'label' => 'Kare 65 x 65 mm', 'sort_order' => 30],
            ],
            'card_orientation' => [
                ['value' => 'yatay', 'label' => 'Yatay', 'sort_order' => 10],
                ['value' => 'dikey', 'label' => 'Dikey', 'sort_order' => 20],
            ],
            'print_sides' => [
                ['value' => 'tek_yon', 'label' => 'Tek Yön', 'sort_order' => 10],
                ['value' => 'cift_yon', 'label' => 'Çift Yön', 'sort_order' => 20],
            ],
            'material_type' => [
                ['value' => 'mat_kuse_350gr', 'label' => 'Mat Kuşe 350 gr', 'sort_order' => 10],
                ['value' => 'parlak_kuse_350gr', 'label' => 'Parlak Kuşe 350 gr', 'sort_order' => 20],
                ['value' => 'tuale_350gr', 'label' => 'Tuale 350 gr', 'sort_order' => 30],
                ['value' => 'kraft_350gr', 'label' => 'Kraft 350 gr', 'sort_order' => 40],
                ['value' => 'ozel_dokulu', 'label' => 'Özel Dokulu Kağıt', 'sort_order' => 50],
            ],
            'lamination_finish' => [
                ['value' => 'mat_selefon', 'label' => 'Mat Selefon', 'sort_order' => 10],
                ['value' => 'parlak_selefon', 'label' => 'Parlak Selefon', 'sort_order' => 20],
                ['value' => 'soft_touch', 'label' => 'Soft Touch', 'sort_order' => 30],
                ['value' => 'selefonsuz', 'label' => 'Selefonsuz', 'sort_order' => 40],
            ],
            'design_style' => [
                ['value' => 'kurumsal_minimal', 'label' => 'Kurumsal ve Minimal', 'sort_order' => 10],
                ['value' => 'premium_luks', 'label' => 'Premium / Lüks', 'sort_order' => 20],
                ['value' => 'modern_dinamik', 'label' => 'Modern ve Dinamik', 'sort_order' => 30],
                ['value' => 'sade_okunakli', 'label' => 'Sade ve Okunaklı', 'sort_order' => 40],
            ],
            'color_preferences' => [
                ['value' => '#0F2747', 'label' => 'Lacivert', 'sort_order' => 10],
                ['value' => '#0A2F2F', 'label' => 'Koyu Petrol', 'sort_order' => 20],
                ['value' => '#111827', 'label' => 'Antrasit Siyah', 'sort_order' => 30],
                ['value' => '#2563EB', 'label' => 'Kraliyet Mavisi', 'sort_order' => 40],
                ['value' => '#065F46', 'label' => 'Zümrüt Yeşili', 'sort_order' => 50],
                ['value' => '#7C3AED', 'label' => 'Mor', 'sort_order' => 60],
                ['value' => '#BE123C', 'label' => 'Bordo', 'sort_order' => 70],
                ['value' => '#EA580C', 'label' => 'Turuncu', 'sort_order' => 80],
                ['value' => '#CA8A04', 'label' => 'Altın Sarısı', 'sort_order' => 90],
                ['value' => '#F3F4F6', 'label' => 'Kırık Beyaz', 'sort_order' => 100],
            ],
            'special_finish' => [
                ['value' => 'yok', 'label' => 'Yok', 'sort_order' => 10],
                ['value' => 'altin_yaldiz', 'label' => 'Altın Yaldız', 'sort_order' => 20],
                ['value' => 'gumus_yaldiz', 'label' => 'Gümüş Yaldız', 'sort_order' => 30],
                ['value' => 'kismi_lak', 'label' => 'Kısmi Lak', 'sort_order' => 40],
                ['value' => 'goffre_kabartma', 'label' => 'Gofre / Kabartma', 'sort_order' => 50],
            ],
        ];

        foreach ($option_map as $field_key => $options) {
            $field_id_stmt->execute([$field_key]);
            $field_id = (int) ($field_id_stmt->fetchColumn() ?: 0);
            if ($field_id <= 0) {
                continue;
            }

            $deactivate_option_stmt->execute([$field_id]);

            foreach ($options as $option) {
                $option_stmt->execute([
                    $field_id,
                    $option['value'],
                    $option['label'],
                    $option['sort_order'],
                ]);
            }
        }
    }
}

if (!function_exists('df_parse_package_list')) {
    function df_parse_package_list(?string $csv): array
    {
        if (!is_string($csv) || trim($csv) === '') {
            return [];
        }

        $allowed = ['classic', 'smart', 'panel'];
        $parts = preg_split('/\s*,\s*/', trim($csv)) ?: [];
        $parts = array_map(static fn(string $value): string => strtolower(trim($value)), $parts);
        $parts = array_values(array_filter(array_unique($parts), static fn(string $value): bool => in_array($value, $allowed, true)));
        return $parts;
    }
}

if (!function_exists('df_field_is_visible_for_package')) {
    function df_field_is_visible_for_package(array $field, string $package): bool
    {
        $visible_packages = df_parse_package_list((string) ($field['show_on_packages'] ?? ''));
        if ($visible_packages === []) {
            return true;
        }
        return in_array(strtolower(trim($package)), $visible_packages, true);
    }
}

if (!function_exists('df_field_is_required_for_package')) {
    function df_field_is_required_for_package(array $field, string $package): bool
    {
        $required_packages = df_parse_package_list((string) ($field['required_on_packages'] ?? ''));
        if ($required_packages !== []) {
            return in_array(strtolower(trim($package)), $required_packages, true);
        }
        return (int) ($field['is_required'] ?? 0) === 1;
    }
}

if (!function_exists('df_get_form_fields')) {
    function df_get_form_fields(PDO $pdo, bool $include_inactive = false): array
    {
        df_ensure_dynamic_form_schema($pdo);
        df_seed_default_form_fields($pdo);
        df_seed_print_brief_fields($pdo);

        $sql = 'SELECT * FROM form_fields';
        if (!$include_inactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';

        $stmt = $pdo->query($sql);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($fields === []) {
            return [];
        }

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $fields);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $opt_sql = "SELECT * FROM form_field_options WHERE field_id IN ({$placeholders})";
        if (!$include_inactive) {
            $opt_sql .= ' AND is_active = 1';
        }
        $opt_sql .= ' ORDER BY sort_order ASC, id ASC';

        $opt_stmt = $pdo->prepare($opt_sql);
        $opt_stmt->execute($ids);
        $options = $opt_stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($options as $option) {
            $field_id = (int) ($option['field_id'] ?? 0);
            if (!isset($grouped[$field_id])) {
                $grouped[$field_id] = [];
            }
            $grouped[$field_id][] = $option;
        }

        foreach ($fields as &$field) {
            $field_id = (int) ($field['id'] ?? 0);
            $field['options'] = $grouped[$field_id] ?? [];
        }
        unset($field);

        return $fields;
    }
}

if (!function_exists('df_get_field_by_key')) {
    function df_get_field_by_key(PDO $pdo, string $field_key): ?array
    {
        df_ensure_dynamic_form_schema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM form_fields WHERE field_key = ? LIMIT 1');
        $stmt->execute([df_normalize_field_key($field_key)]);
        $field = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($field) ? $field : null;
    }
}

if (!function_exists('df_normalize_field_key')) {
    function df_normalize_field_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = trim($value, '_');
        if ($value === '') {
            return '';
        }
        return substr($value, 0, 80);
    }
}

if (!function_exists('df_normalize_option_value')) {
    function df_normalize_option_value(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = $value;
        }

        return substr($slug, 0, 120);
    }
}

if (!function_exists('df_create_field')) {
    function df_create_field(PDO $pdo, array $payload, int $actor_id = 0): int
    {
        $field_key = df_normalize_field_key((string) ($payload['field_key'] ?? ''));
        $field_label = trim((string) ($payload['field_label'] ?? ''));
        $field_type = strtolower(trim((string) ($payload['field_type'] ?? 'text')));
        $placeholder = trim((string) ($payload['placeholder'] ?? ''));
        $help_text = trim((string) ($payload['help_text'] ?? ''));
        $default_value = trim((string) ($payload['default_value'] ?? ''));
        $show_on_packages = trim((string) ($payload['show_on_packages'] ?? ''));
        $required_on_packages = trim((string) ($payload['required_on_packages'] ?? ''));
        $is_required = !empty($payload['is_required']) ? 1 : 0;
        $is_active = array_key_exists('is_active', $payload) ? (int) (!empty($payload['is_active'])) : 1;
        $sort_order = (int) ($payload['sort_order'] ?? 999);

        $allowed_types = ['text', 'textarea', 'select', 'email', 'url', 'tel', 'number'];
        if ($field_key === '' || $field_label === '' || !in_array($field_type, $allowed_types, true)) {
            throw new RuntimeException('invalid_field_payload');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO form_fields
             (field_key, field_label, field_type, placeholder, help_text, default_value, show_on_packages, required_on_packages, is_required, is_active, sort_order, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $field_key,
            $field_label,
            $field_type,
            $placeholder !== '' ? $placeholder : null,
            $help_text !== '' ? $help_text : null,
            $default_value,
            $show_on_packages !== '' ? $show_on_packages : null,
            $required_on_packages !== '' ? $required_on_packages : null,
            $is_required,
            $is_active,
            $sort_order,
            $actor_id > 0 ? $actor_id : null,
            $actor_id > 0 ? $actor_id : null,
        ]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('df_create_option')) {
    function df_create_option(PDO $pdo, int $field_id, array $payload): int
    {
        $label = trim((string) ($payload['option_label'] ?? ''));
        $raw_value = trim((string) ($payload['option_value'] ?? ''));
        $sort_order = (int) ($payload['sort_order'] ?? 999);
        $is_active = array_key_exists('is_active', $payload) ? (int) (!empty($payload['is_active'])) : 1;

        if ($label === '') {
            throw new RuntimeException('invalid_option_payload');
        }

        $value = df_normalize_option_value($raw_value !== '' ? $raw_value : $label);
        if ($value === '') {
            throw new RuntimeException('invalid_option_payload');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO form_field_options (field_id, option_value, option_label, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 option_label = VALUES(option_label),
                 sort_order = VALUES(sort_order),
                 is_active = VALUES(is_active)"
        );
        $stmt->execute([$field_id, $value, $label, $sort_order, $is_active]);

        return (int) ($pdo->lastInsertId() ?: 0);
    }
}

if (!function_exists('df_upsert_order_answers')) {
    function df_upsert_order_answers(PDO $pdo, int $order_id, array $answers): void
    {
        if ($order_id <= 0 || $answers === []) {
            return;
        }

        df_ensure_dynamic_form_schema($pdo);

        $stmt = $pdo->prepare(
            "INSERT INTO order_form_answers (order_id, field_key, field_label, value_text, value_source)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                field_label = VALUES(field_label),
                value_text = VALUES(value_text),
                value_source = VALUES(value_source)"
        );

        foreach ($answers as $answer) {
            $field_key = df_normalize_field_key((string) ($answer['field_key'] ?? ''));
            $field_label = trim((string) ($answer['field_label'] ?? $field_key));
            $value_text = (string) ($answer['value_text'] ?? '');
            $value_source = (string) ($answer['value_source'] ?? 'customer');

            if ($field_key === '' || $field_label === '') {
                continue;
            }
            if (!in_array($value_source, ['customer', 'default'], true)) {
                $value_source = 'customer';
            }

            $stmt->execute([$order_id, $field_key, $field_label, $value_text, $value_source]);
        }
    }
}

if (!function_exists('df_get_order_answers')) {
    function df_get_order_answers(PDO $pdo, int $order_id): array
    {
        if ($order_id <= 0 || !df_table_exists($pdo, 'order_form_answers')) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT * FROM order_form_answers WHERE order_id = ? ORDER BY id ASC');
        $stmt->execute([$order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('df_build_illustrator_xml')) {
    function df_build_illustrator_xml(array $variables, string $dataset_name = 'DataSet 1'): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $dataset_name = trim($dataset_name);
        if ($dataset_name === '') {
            $dataset_name = 'DataSet 1';
        }

        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<variableSets xmlns="http://ns.adobe.com/Variables/1.0/">';
        $xml[] = '  <variables></variables>';
        $xml[] = '  <v:sampleDataSets xmlns:v="http://ns.adobe.com/Variables/1.0/">';
        $xml[] = '    <v:sampleDataSet dataSetName="' . $escape($dataset_name) . '">';

        foreach ($variables as $key => $value) {
            $safe_key = df_to_ai_variable_key((string) $key);
            if ($safe_key === '') {
                continue;
            }
            $safe_value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $xml[] = '      <v:sampleData key="' . $escape($safe_key) . '">' . $escape((string) $safe_value) . '</v:sampleData>';
        }

        $xml[] = '    </v:sampleDataSet>';
        $xml[] = '  </v:sampleDataSets>';
        $xml[] = '</variableSets>';

        return implode("\n", $xml);
    }
}

if (!function_exists('df_to_ai_variable_key')) {
    function df_to_ai_variable_key(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if (!is_string($transliterated) || $transliterated === '') {
            $transliterated = $raw;
        }

        $key = strtolower($transliterated);
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');
        if ($key === '') {
            $key = 'field';
        }
        return substr($key, 0, 120);
    }
}
