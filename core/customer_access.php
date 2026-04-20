<?php

require_once __DIR__ . '/subscription.php';

if (!function_exists('qrk_customer_access_table_has_column')) {
    function qrk_customer_access_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        if (!qrk_table_exists($pdo, $table)) {
            return false;
        }

        $table_escaped = str_replace('`', '``', $table);
        $column_escaped = str_replace("'", "''", $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
        return (bool)$stmt->fetch();
    }
}

if (!function_exists('qrk_normalize_package_slug')) {
    function qrk_normalize_package_slug(?string $package_slug): string
    {
        $normalized = strtolower(trim((string)$package_slug));
        return in_array($normalized, ['classic', 'smart', 'panel'], true) ? $normalized : '';
    }
}

if (!function_exists('qrk_get_package_default_definitions')) {
    function qrk_get_package_default_definitions(): array
    {
        return [
            'classic' => [
                'slug' => 'classic',
                'label' => 'Klasik Paket',
                'short_label' => 'Klasik',
                'description' => 'Sadece baskılı kartvizit tasarımı ve baskı süreci sunar.',
                'price' => 299.00,
                'included_revisions' => 2,
                'has_digital_profile' => false,
                'has_physical_print' => true,
                'is_active' => true,
                'included_features' => [
                    'Baskılı kartvizit siparişi',
                    '2 ücretsiz revize',
                    'Tasarım takip ekranı',
                ],
                'excluded_features' => [
                    'Dijital profil linki',
                    'QR paylaşımı',
                    'Sosyal link modülü',
                ],
                'register_title' => 'Klasik',
                'register_badge' => '',
                'register_price_text' => '799 ₺',
                'register_subtitle' => 'Sadece baskı hizmeti, dijital panel yok',
                'register_features' => [
                    'Standart fiziksel kartvizit baskısı',
                    '2 revize hakkı',
                    'Kurumsal logo ve temel tasarım desteği',
                ],
                'register_note' => 'Basılı kartvizit odaklı sade çözüm arayan müşteriler için uygundur.',
                'register_panel_text' => 'Klasik pakette dijital panel bulunmaz. Bu nedenle aşağıdaki dijital panel alanları pasif durumdadır.',
            ],
            'smart' => [
                'slug' => 'smart',
                'label' => 'Akıllı Paket',
                'short_label' => 'Akıllı',
                'description' => 'Dijital profil ile baskılı kartviziti bir arada sunar.',
                'price' => 1200.00,
                'included_revisions' => 2,
                'has_digital_profile' => true,
                'has_physical_print' => true,
                'is_active' => true,
                'included_features' => [
                    'Dijital profil ve QR linki',
                    'Baskılı kartvizit siparişi',
                    '2 ücretsiz revize',
                    'Sosyal link alanı',
                ],
                'excluded_features' => [],
                'register_title' => '1000 Baskı + 1 Yıllık Erişim',
                'register_badge' => 'EN ÇOK TERCİH EDİLEN',
                'register_price_text' => '1.200 ₺ / yıl',
                'register_subtitle' => 'Panel + 1000 baskı + dinamik QR',
                'register_features' => [
                    '1000 adet fiziksel kartvizit baskısı dahildir',
                    '1 yıllık dijital panel erişimi tek ödeme ile aktif olur',
                    'Dinamik QR ile bilgi güncelleme',
                    'Tek seferlik yıllık ödeme modeli',
                ],
                'register_note' => 'Dijital profil de isteyen müşteriler için en kapsamlı seçenek Akıllı pakettir.',
                'register_panel_text' => 'Akıllı pakette dijital panel ve fiziksel baskı birlikte gelir. 1000 baskı dahildir ve 1 yıllık erişim tek ödeme ile tanımlanır.',
            ],
            'panel' => [
                'slug' => 'panel',
                'label' => 'Dijital Panel Paketi',
                'short_label' => 'Panel',
                'description' => 'Sadece dijital kartvizit, profil linki ve QR deneyimi sunar.',
                'price' => 700.00,
                'included_revisions' => 0,
                'has_digital_profile' => true,
                'has_physical_print' => false,
                'is_active' => true,
                'included_features' => [
                    'Dijital profil ve QR linki',
                    'Sosyal link alanı',
                    'Anında yayınlanabilir profil',
                ],
                'excluded_features' => [
                    'Baskılı kartvizit siparişi',
                    'Basılı tasarım revizesi',
                ],
                'register_title' => 'Sadece Panel',
                'register_badge' => '',
                'register_price_text' => '700 ₺ / yıl',
                'register_subtitle' => 'Sadece dijital kartvizit deneyimi',
                'register_features' => [
                    'Fiziksel baskı olmadan tamamen dijital profil',
                    'QR ile profil paylaşımı ve panel yönetimi',
                    'Tek seferlik yıllık ödeme modeli',
                ],
                'register_note' => 'Sadece dijital kartvizit isteyen müşteriler için yıllık erişim odaklı çözümdür.',
                'register_panel_text' => 'Sadece Panel paketinde dijital kartvizit siteniz aktif olur. Bu ayarlar profilinizi doğrudan yayına hazırlar.',
            ],
        ];
    }
}

if (!function_exists('qrk_get_unknown_package_definition')) {
    function qrk_get_unknown_package_definition(): array
    {
        return [
            'slug' => '',
            'label' => 'Paket Tanımsız',
            'short_label' => 'Tanımsız',
            'description' => 'Bu hesap için aktif bir paket bulunamadı.',
            'price' => 0.0,
            'included_revisions' => 0,
            'has_digital_profile' => false,
            'has_physical_print' => false,
            'is_active' => false,
            'included_features' => [],
            'excluded_features' => [],
            'register_title' => 'Paket Tanımsız',
            'register_badge' => '',
            'register_price_text' => '',
            'register_subtitle' => 'Aktif bir paket bulunamadı.',
            'register_features' => [],
            'register_note' => '',
            'register_panel_text' => 'Bu hesap için dijital panel aktif değil.',
        ];
    }
}

if (!function_exists('qrk_decode_package_list')) {
    function qrk_decode_package_list($raw_value, array $fallback): array
    {
        if (!is_string($raw_value) || trim($raw_value) === '') {
            return $fallback;
        }

        $decoded = json_decode($raw_value, true);
        if (!is_array($decoded)) {
            return $fallback;
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return $items !== [] ? $items : $fallback;
    }
}

if (!function_exists('qrk_ensure_package_content_schema')) {
    function qrk_ensure_package_content_schema(PDO $pdo): void
    {
        static $checked = false;

        if ($checked || !qrk_table_exists($pdo, 'packages')) {
            return;
        }

        $checked = true;

        $columns = [
            'display_label' => "ALTER TABLE packages ADD COLUMN display_label VARCHAR(100) NULL AFTER name",
            'short_label' => "ALTER TABLE packages ADD COLUMN short_label VARCHAR(60) NULL AFTER display_label",
            'description_text' => "ALTER TABLE packages ADD COLUMN description_text TEXT NULL AFTER included_revisions",
            'included_features_json' => "ALTER TABLE packages ADD COLUMN included_features_json TEXT NULL AFTER description_text",
            'excluded_features_json' => "ALTER TABLE packages ADD COLUMN excluded_features_json TEXT NULL AFTER included_features_json",
            'register_title' => "ALTER TABLE packages ADD COLUMN register_title VARCHAR(150) NULL AFTER excluded_features_json",
            'register_subtitle' => "ALTER TABLE packages ADD COLUMN register_subtitle VARCHAR(255) NULL AFTER register_title",
            'register_badge' => "ALTER TABLE packages ADD COLUMN register_badge VARCHAR(120) NULL AFTER register_subtitle",
            'register_price_text' => "ALTER TABLE packages ADD COLUMN register_price_text VARCHAR(120) NULL AFTER register_badge",
            'register_features_json' => "ALTER TABLE packages ADD COLUMN register_features_json TEXT NULL AFTER register_price_text",
            'register_note' => "ALTER TABLE packages ADD COLUMN register_note TEXT NULL AFTER register_features_json",
            'register_panel_text' => "ALTER TABLE packages ADD COLUMN register_panel_text TEXT NULL AFTER register_note",
        ];

        foreach ($columns as $column => $sql) {
            if (qrk_customer_access_table_has_column($pdo, 'packages', $column)) {
                continue;
            }

            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Runtime should keep working even if schema change cannot be applied.
            }
        }
    }
}

if (!function_exists('qrk_seed_package_content_defaults')) {
    function qrk_seed_package_content_defaults(PDO $pdo): void
    {
        static $seeded = false;

        if ($seeded || !qrk_table_exists($pdo, 'packages')) {
            return;
        }

        $seeded = true;
        qrk_ensure_package_content_schema($pdo);

        $defaults = qrk_get_package_default_definitions();
        $select = $pdo->prepare('SELECT * FROM packages WHERE slug = ? LIMIT 1');

        foreach ($defaults as $slug => $definition) {
            $select->execute([$slug]);
            $row = $select->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $insert = $pdo->prepare(
                    'INSERT INTO packages (
                        name, display_label, short_label, slug, price, has_physical_print, has_digital_profile, has_qr_code,
                        included_revisions, description_text, included_features_json, excluded_features_json, register_title,
                        register_subtitle, register_badge, register_price_text, register_features_json, register_note,
                        register_panel_text, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $definition['label'],
                    $definition['label'],
                    $definition['short_label'],
                    $slug,
                    (float)$definition['price'],
                    $definition['has_physical_print'] ? 1 : 0,
                    $definition['has_digital_profile'] ? 1 : 0,
                    $definition['has_digital_profile'] ? 1 : 0,
                    (int)$definition['included_revisions'],
                    $definition['description'],
                    json_encode($definition['included_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($definition['excluded_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $definition['register_title'],
                    $definition['register_subtitle'],
                    $definition['register_badge'],
                    $definition['register_price_text'],
                    json_encode($definition['register_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $definition['register_note'],
                    $definition['register_panel_text'],
                    $definition['is_active'] ? 1 : 0,
                ]);
                continue;
            }

            $updates = [];
            $params = [];
            $mapped_fields = [
                'display_label' => $definition['label'],
                'short_label' => $definition['short_label'],
                'description_text' => $definition['description'],
                'included_features_json' => json_encode($definition['included_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'excluded_features_json' => json_encode($definition['excluded_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'register_title' => $definition['register_title'],
                'register_subtitle' => $definition['register_subtitle'],
                'register_badge' => $definition['register_badge'],
                'register_price_text' => $definition['register_price_text'],
                'register_features_json' => json_encode($definition['register_features'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'register_note' => $definition['register_note'],
                'register_panel_text' => $definition['register_panel_text'],
            ];

            foreach ($mapped_fields as $column => $value) {
                if (!array_key_exists($column, $row) || trim((string)$row[$column]) !== '') {
                    continue;
                }

                $updates[] = "{$column} = ?";
                $params[] = $value;
            }

            if (array_key_exists('name', $row) && trim((string)$row['name']) === '') {
                $updates[] = 'name = ?';
                $params[] = $definition['label'];
            }

            if ($updates === []) {
                continue;
            }

            $params[] = $slug;
            $update = $pdo->prepare('UPDATE packages SET ' . implode(', ', $updates) . ' WHERE slug = ? LIMIT 1');
            $update->execute($params);
        }
    }
}

if (!function_exists('qrk_get_all_package_definitions')) {
    function qrk_get_all_package_definitions(?PDO $pdo = null): array
    {
        $definitions = qrk_get_package_default_definitions();

        if (!$pdo instanceof PDO || !qrk_table_exists($pdo, 'packages')) {
            return $definitions;
        }

        qrk_seed_package_content_defaults($pdo);

        $stmt = $pdo->query("SELECT * FROM packages WHERE slug IN ('classic', 'smart', 'panel') ORDER BY FIELD(slug, 'classic', 'smart', 'panel')");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($rows as $row) {
            $slug = qrk_normalize_package_slug((string)($row['slug'] ?? ''));
            if ($slug === '' || !isset($definitions[$slug])) {
                continue;
            }

            $fallback = $definitions[$slug];
            $definitions[$slug] = [
                'slug' => $slug,
                'label' => trim((string)($row['display_label'] ?? $row['name'] ?? $fallback['label'])) ?: $fallback['label'],
                'short_label' => trim((string)($row['short_label'] ?? $fallback['short_label'])) ?: $fallback['short_label'],
                'description' => trim((string)($row['description_text'] ?? $fallback['description'])) ?: $fallback['description'],
                'price' => isset($row['price']) ? (float)$row['price'] : (float)$fallback['price'],
                'included_revisions' => isset($row['included_revisions']) ? (int)$row['included_revisions'] : (int)$fallback['included_revisions'],
                'has_digital_profile' => (bool)($row['has_digital_profile'] ?? $fallback['has_digital_profile']),
                'has_physical_print' => (bool)($row['has_physical_print'] ?? $fallback['has_physical_print']),
                'is_active' => array_key_exists('is_active', $row) ? (bool)$row['is_active'] : (bool)$fallback['is_active'],
                'included_features' => qrk_decode_package_list($row['included_features_json'] ?? null, $fallback['included_features']),
                'excluded_features' => qrk_decode_package_list($row['excluded_features_json'] ?? null, $fallback['excluded_features']),
                'register_title' => trim((string)($row['register_title'] ?? $fallback['register_title'])) ?: $fallback['register_title'],
                'register_badge' => trim((string)($row['register_badge'] ?? $fallback['register_badge'])),
                'register_price_text' => trim((string)($row['register_price_text'] ?? $fallback['register_price_text'])) ?: $fallback['register_price_text'],
                'register_subtitle' => trim((string)($row['register_subtitle'] ?? $fallback['register_subtitle'])) ?: $fallback['register_subtitle'],
                'register_features' => qrk_decode_package_list($row['register_features_json'] ?? null, $fallback['register_features']),
                'register_note' => trim((string)($row['register_note'] ?? $fallback['register_note'])) ?: $fallback['register_note'],
                'register_panel_text' => trim((string)($row['register_panel_text'] ?? $fallback['register_panel_text'])) ?: $fallback['register_panel_text'],
            ];
        }

        return $definitions;
    }
}

if (!function_exists('qrk_get_package_definition')) {
    function qrk_get_package_definition(?string $package_slug, ?PDO $pdo = null): array
    {
        $normalized = qrk_normalize_package_slug($package_slug);
        if ($normalized === '') {
            return qrk_get_unknown_package_definition();
        }

        $definitions = qrk_get_all_package_definitions($pdo);
        return $definitions[$normalized] ?? qrk_get_unknown_package_definition();
    }
}

if (!function_exists('qrk_ensure_customer_access_schema')) {
    function qrk_ensure_customer_access_schema(PDO $pdo): void
    {
        static $checked = false;

        if ($checked || !qrk_table_exists($pdo, 'users')) {
            return;
        }

        $checked = true;

        $columns = [
            'default_package_slug' => "ALTER TABLE users ADD COLUMN default_package_slug VARCHAR(50) NULL AFTER role",
            'remaining_order_credits' => "ALTER TABLE users ADD COLUMN remaining_order_credits INT NOT NULL DEFAULT 0 AFTER default_package_slug",
            'pending_package_slug' => "ALTER TABLE users ADD COLUMN pending_package_slug VARCHAR(50) NULL AFTER remaining_order_credits",
            'pending_package_mode' => "ALTER TABLE users ADD COLUMN pending_package_mode VARCHAR(20) NULL AFTER pending_package_slug",
        ];

        foreach ($columns as $column => $sql) {
            if (qrk_customer_access_table_has_column($pdo, 'users', $column)) {
                continue;
            }

            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Graceful fallback keeps legacy session-based flow working.
            }
        }
    }
}

if (!function_exists('qrk_assign_customer_package')) {
    function qrk_assign_customer_package(PDO $pdo, int $user_id, string $package_slug, int $order_credits = 1): void
    {
        $package = qrk_normalize_package_slug($package_slug);
        if ($user_id <= 0 || $package === '') {
            return;
        }

        qrk_ensure_customer_access_schema($pdo);
        if (
            !qrk_customer_access_table_has_column($pdo, 'users', 'default_package_slug')
            || !qrk_customer_access_table_has_column($pdo, 'users', 'remaining_order_credits')
        ) {
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE users
             SET default_package_slug = ?, remaining_order_credits = ?
             WHERE id = ?'
        );
        $stmt->execute([$package, max(0, $order_credits), $user_id]);

        if (qrk_customer_access_table_has_column($pdo, 'users', 'pending_package_slug')) {
            $pdo->prepare('UPDATE users SET pending_package_slug = NULL WHERE id = ?')->execute([$user_id]);
        }
    }
}

if (!function_exists('qrk_set_customer_pending_package')) {
    function qrk_set_customer_pending_package(PDO $pdo, int $user_id, ?string $package_slug, string $mode = 'direct'): void
    {
        if ($user_id <= 0) {
            return;
        }

        qrk_ensure_customer_access_schema($pdo);
        if (!qrk_customer_access_table_has_column($pdo, 'users', 'pending_package_slug')) {
            return;
        }

        $normalized = qrk_normalize_package_slug($package_slug);
        $value = $normalized !== '' ? $normalized : null;
        $normalized_mode = in_array($mode, ['direct', 'preview'], true) ? $mode : 'direct';

        if (qrk_customer_access_table_has_column($pdo, 'users', 'pending_package_mode')) {
            $stmt = $pdo->prepare('UPDATE users SET pending_package_slug = ?, pending_package_mode = ? WHERE id = ?');
            $stmt->execute([$value, $value !== null ? $normalized_mode : null, $user_id]);
            return;
        }

        $stmt = $pdo->prepare('UPDATE users SET pending_package_slug = ? WHERE id = ?');
        $stmt->execute([$value, $user_id]);
    }
}

if (!function_exists('qrk_get_customer_pending_package_slug')) {
    function qrk_get_customer_pending_package_slug(PDO $pdo, int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        qrk_ensure_customer_access_schema($pdo);
        if (!qrk_customer_access_table_has_column($pdo, 'users', 'pending_package_slug')) {
            return '';
        }

        $stmt = $pdo->prepare('SELECT pending_package_slug FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        return qrk_normalize_package_slug((string)$stmt->fetchColumn());
    }
}

if (!function_exists('qrk_get_customer_pending_package_mode')) {
    function qrk_get_customer_pending_package_mode(PDO $pdo, int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        qrk_ensure_customer_access_schema($pdo);
        if (!qrk_customer_access_table_has_column($pdo, 'users', 'pending_package_mode')) {
            return '';
        }

        $stmt = $pdo->prepare('SELECT pending_package_mode FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $mode = strtolower(trim((string)$stmt->fetchColumn()));
        return in_array($mode, ['direct', 'preview'], true) ? $mode : '';
    }
}

if (!function_exists('qrk_customer_has_preview_mode')) {
    function qrk_customer_has_preview_mode(PDO $pdo, int $user_id): bool
    {
        return qrk_get_customer_pending_package_slug($pdo, $user_id) !== ''
            && qrk_get_customer_pending_package_mode($pdo, $user_id) === 'preview';
    }
}

if (!function_exists('qrk_get_customer_package_slug')) {
    function qrk_get_customer_package_slug(PDO $pdo, int $user_id, ?string $fallback_package = null): string
    {
        if ($user_id <= 0) {
            return qrk_normalize_package_slug($fallback_package);
        }

        qrk_ensure_customer_access_schema($pdo);
        if (qrk_customer_access_table_has_column($pdo, 'users', 'default_package_slug')) {
            $stmt = $pdo->prepare('SELECT default_package_slug FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $package = qrk_normalize_package_slug((string)$stmt->fetchColumn());
            if ($package !== '') {
                return $package;
            }
        }

        $session_package = qrk_normalize_package_slug((string)($_SESSION['default_order_package'] ?? ''));
        if ($session_package !== '') {
            return $session_package;
        }

        return qrk_normalize_package_slug($fallback_package);
    }
}

if (!function_exists('qrk_get_customer_remaining_order_credits')) {
    function qrk_get_customer_remaining_order_credits(PDO $pdo, int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $package_slug = qrk_get_customer_package_slug($pdo, $user_id);
        if ($package_slug === '') {
            return 0;
        }

        return qrk_customer_has_existing_order($pdo, $user_id) ? 0 : 1;
    }
}

if (!function_exists('qrk_customer_has_existing_order')) {
    function qrk_customer_has_existing_order(PDO $pdo, int $user_id): bool
    {
        if ($user_id <= 0 || !qrk_table_exists($pdo, 'orders')) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('qrk_user_can_create_order')) {
    function qrk_user_can_create_order(PDO $pdo, int $user_id): bool
    {
        if (qrk_get_customer_package_slug($pdo, $user_id) === '') {
            return false;
        }

        return !qrk_customer_has_existing_order($pdo, $user_id);
    }
}

if (!function_exists('qrk_consume_customer_order_credit')) {
    function qrk_consume_customer_order_credit(PDO $pdo, int $user_id): void
    {
        return;
    }
}

if (!function_exists('qrk_get_customer_package_state')) {
    function qrk_get_customer_package_state(PDO $pdo, int $user_id, ?string $fallback_package = null): array
    {
        $package_slug = qrk_get_customer_package_slug($pdo, $user_id, $fallback_package);
        $definition = qrk_get_package_definition($package_slug, $pdo);
        $pending_package_slug = qrk_get_customer_pending_package_slug($pdo, $user_id);
        $pending_package_mode = qrk_get_customer_pending_package_mode($pdo, $user_id);
        $has_existing_order = qrk_customer_has_existing_order($pdo, $user_id);
        $remaining_order_credits = qrk_get_customer_remaining_order_credits($pdo, $user_id);
        $can_create_order = $package_slug !== '' && !$has_existing_order;
        $lock_reason = '';
        if ($package_slug === '') {
            $lock_reason = 'no_package';
        } elseif ($has_existing_order) {
            $lock_reason = 'order_limit_reached';
        }

        return [
            'package_slug' => $package_slug,
            'pending_package_slug' => $pending_package_slug,
            'pending_package_mode' => $pending_package_mode,
            'pending_definition' => qrk_get_package_definition($pending_package_slug, $pdo),
            'definition' => $definition,
            'remaining_order_credits' => $remaining_order_credits,
            'can_create_order' => $can_create_order,
            'has_existing_order' => $has_existing_order,
            'lock_reason' => $lock_reason,
        ];
    }
}
