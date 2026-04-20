<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';
require_once '../core/dynamic_form.php';
require_once '../core/social_branding.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

$stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Müşteri'];
$user_name = trim((string) ($user['name'] ?? 'Müşteri'));
if ($user_name === '') {
    $user_name = 'Müşteri';
}

$stmt = $pdo->prepare('SELECT * FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$user_id]);
$profile = $stmt->fetch() ?: ['company' => '', 'title' => ''];

$notice_map = [
    'created' => 'Siparişiniz alındı. Tasarım ekibi en kısa sürede süreci başlatacaktır.',
    'package_selected' => 'Paketiniz tanımlandı. ?imdi sipariş formunu doldurabilirsiniz.',
    'preview_mode' => 'İnceleme modu açıldı. Formu doldurabilirsiniz; siparişi göndermeye çalıştığınızda satın alma hazırlığı ekranına yönlendirileceksiniz.',
];
$error_map = [
    'csrf' => 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.',
    'required_fields' => 'Lütfen zorunlu alanları doldurun.',
    'logo_upload_error' => 'Logo yüklemesi sırasında bir hata oluştu.',
    'logo_too_large' => 'Logo dosyası en fazla 5 MB olabilir.',
    'logo_invalid_type' => 'Logo için sadece JPG, PNG veya WEBP yükleyebilirsiniz.',
    'logo_dir_failed' => 'Logo klasörü oluşturulamadı. Lütfen daha sonra tekrar deneyin.',
    'logo_move_failed' => 'Logo kaydedilemedi. Lütfen farklı bir dosya ile tekrar deneyin.',
    'profile_photo_upload_error' => 'Profil fotoğrafı yüklenirken bir hata oluştu.',
    'profile_photo_too_large' => 'Profil fotoğrafı en fazla 5 MB olabilir.',
    'profile_photo_invalid_type' => 'Profil fotoğrafı için sadece JPG, PNG veya WEBP yükleyebilirsiniz.',
    'profile_photo_dir_failed' => 'Profil fotoğrafı klasörü oluşturulamadı.',
    'profile_photo_move_failed' => 'Profil fotoğrafı kaydedilemedi.',
    'cover_photo_upload_error' => 'Kapak fotoğrafı yüklenirken bir hata oluştu.',
    'cover_photo_too_large' => 'Kapak fotoğrafı en fazla 5 MB olabilir.',
    'cover_photo_invalid_type' => 'Kapak fotoğrafı için sadece JPG, PNG veya WEBP yükleyebilirsiniz.',
    'cover_photo_dir_failed' => 'Kapak fotoğrafı klasörü oluşturulamadı.',
    'cover_photo_move_failed' => 'Kapak fotoğrafı kaydedilemedi.',
    'social_logo_upload_error' => 'Sosyal platform logosu yüklenirken bir hata oluştu.',
    'social_logo_too_large' => 'Sosyal platform logosu en fazla 2 MB olabilir.',
    'social_logo_invalid_type' => 'Sosyal platform logosu için sadece JPG, PNG veya WEBP yükleyebilirsiniz.',
    'social_logo_dir_failed' => 'Sosyal platform logo klasörü oluşturulamadı.',
    'social_logo_move_failed' => 'Sosyal platform logosu kaydedilemedi.',
    'create_failed' => 'Sipariş oluşturulurken bir hata oluştu.',
];

$notice_key = trim((string) ($_GET['order_notice'] ?? ''));
$error_key = trim((string) ($_GET['order_error'] ?? ''));
$notice_message = $notice_map[$notice_key] ?? '';
$error_message = $error_map[$error_key] ?? '';
if ($error_key === 'order_limit_reached') {
    $error_message = 'Sipariş hakkınızı kullandınız. Baskı siparişi tekrar açılamaz; web sitesi tarafını profil ekranından güncelleyebilirsiniz.';
}
if ($error_key === 'no_credit') {
    $error_message = 'Aktif sipariş hakkınız bulunmuyor. Yeni paket tanımlaması için destek ekibiyle iletişime geçin.';
}
if ($error_key === 'no_credit' && $error_message === '') {
    $error_message = 'Aktif sipariş hakkınız bulunmuyor. Yeni paket tanımlaması için destek ekibiyle iletişime geçin.';
}

$order_old_input = $_SESSION['customer_order_old_input'] ?? [];
if (!is_array($order_old_input)) {
    $order_old_input = [];
}
unset($_SESSION['customer_order_old_input']);

if (!function_exists('order_old')) {
    function order_old(string $key, string $default = ''): string
    {
        global $order_old_input;
        $value = $order_old_input[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

$old_dynamic_fields = $order_old_input['dynamic_fields'] ?? [];
if (!is_array($old_dynamic_fields)) {
    $old_dynamic_fields = [];
}

$old_digital_qr = $order_old_input['digital_qr'] ?? [];
if (!is_array($old_digital_qr)) {
    $old_digital_qr = [];
}

if (!function_exists('order_dynamic_old')) {
    function order_dynamic_old(string $field_key, string $default = ''): string
    {
        global $old_dynamic_fields;
        $value = $old_dynamic_fields[$field_key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('order_digital_old')) {
    function order_digital_old(string $key, string $default = ''): string
    {
        global $old_digital_qr;
        $value = $old_digital_qr[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('order_digital_profile_old')) {
    function order_digital_profile_old(string $key, string $default = ''): string
    {
        global $order_old_input;
        $digital_profile = $order_old_input['digital_profile'] ?? [];
        if (!is_array($digital_profile)) {
            return $default;
        }
        $value = $digital_profile[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('order_digital_links_old')) {
    function order_digital_links_old(string $key): array
    {
        global $order_old_input;
        $digital_links = $order_old_input['digital_links'] ?? [];
        if (!is_array($digital_links)) {
            return [];
        }
        $value = $digital_links[$key] ?? [];
        return is_array($value) ? array_values($value) : [];
    }
}

if (!function_exists('normalize_hex_color_for_view')) {
    function normalize_hex_color_for_view(string $value, string $fallback): string
    {
        $candidate = strtoupper(trim($value));
        if (!preg_match('/^#[0-9A-F]{6}$/', $candidate)) {
            return strtoupper($fallback);
        }
        return $candidate;
    }
}

if (!function_exists('normalize_initial_for_view')) {
    function normalize_initial_for_view(string $value, string $fallback = 'A'): string
    {
        $raw = trim($value);
        if ($raw === '') {
            $raw = $fallback;
        }
        $raw = preg_replace('/\s+/u', '', $raw);
        $raw = preg_replace('/\p{C}+/u', '', (string)$raw);
        if (!is_string($raw) || $raw === '') {
            $raw = $fallback;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($raw, 0, 2, 'UTF-8');
        }

        return substr($raw, 0, 2);
    }
}

if (!function_exists('derive_initial_from_name_for_view')) {
    function derive_initial_from_name_for_view(string $name, string $fallback = 'A'): string
    {
        $clean = trim($name);
        if ($clean === '') {
            return normalize_initial_for_view($fallback, 'A');
        }

        $parts = preg_split('/\s+/u', $clean) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $letters .= mb_substr($part, 0, 1, 'UTF-8');
            } else if (type === 'text') {
                $letters .= substr($part, 0, 1);
            }
            if ((function_exists('mb_strlen') ? mb_strlen($letters, 'UTF-8') : strlen($letters)) >= 2) {
                break;
            }
        }

        if ($letters === '') {
            $letters = $clean;
        }

        return normalize_initial_for_view($letters, $fallback);
    }
}

if (!function_exists('table_exists_for_view')) {
    function table_exists_for_view(PDO $pdo, string $table): bool
    {
        $table_escaped = str_replace("'", "''", $table);
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('table_has_column_for_view')) {
    function table_has_column_for_view(PDO $pdo, string $table, string $column): bool
    {
        if (!table_exists_for_view($pdo, $table)) {
            return false;
        }
        $table_escaped = str_replace('`', '``', $table);
        $column_escaped = str_replace("'", "''", $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
        return (bool)$stmt->fetch();
    }
}

$latest_order_package = '';
if (
    table_exists_for_view($pdo, 'orders')
    && table_has_column_for_view($pdo, 'orders', 'package')
) {
    $stmt_latest_order = $pdo->prepare('SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt_latest_order->execute([$user_id]);
    $latest_order_package = strtolower(trim((string) $stmt_latest_order->fetchColumn()));
    if (!in_array($latest_order_package, ['classic', 'smart', 'panel'], true)) {
        $latest_order_package = '';
    }
}

$package_state = qrk_get_customer_package_state($pdo, $user_id, $latest_order_package);
$has_active_package = (string)($package_state['package_slug'] ?? '') !== '';
$pending_package_slug = (string)($package_state['pending_package_slug'] ?? '');
$pending_package_mode = (string)($package_state['pending_package_mode'] ?? '');
$pending_package_definition = (array)($package_state['pending_definition'] ?? qrk_get_unknown_package_definition());
$is_preview_mode = !$has_active_package && $pending_package_slug !== '' && $pending_package_mode === 'preview';
$current_package_definition = $has_active_package ? $package_state['definition'] : ($is_preview_mode ? $pending_package_definition : $package_state['definition']);
$customer_locked_package = $has_active_package ? $package_state['package_slug'] : ($is_preview_mode ? $pending_package_slug : $package_state['package_slug']);
$remaining_order_credits = $package_state['remaining_order_credits'];
$can_create_order = (bool)$package_state['can_create_order'] || $is_preview_mode;

$order_package_catalog = [
    'smart' => [
        'label' => 'Baskılı + Dijital Kartvizit',
        'description' => 'Basılı kartvizit ile birlikte dijital profil ve QR ayarları birlikte hazırlanır.',
    ],
    'classic' => [
        'label' => 'Sadece Baskılı Kartvizit',
        'description' => 'Yalnızca basılı kartvizit siparişi oluşturulur. Dijital profil alanları kapalı kalır.',
    ],
    'panel' => [
        'label' => 'Sadece Dijital Kartvizit',
        'description' => 'Yalnızca dijital profil, linkler ve QR özelleştirmesi hazırlanır.',
    ],
];
$order_package_catalog = [
    'smart' => qrk_get_package_definition('smart'),
    'classic' => qrk_get_package_definition('classic'),
    'panel' => qrk_get_package_definition('panel'),
];

$available_order_packages = [];
if ($customer_locked_package !== '' && isset($order_package_catalog[$customer_locked_package])) {
    $available_order_packages[$customer_locked_package] = $order_package_catalog[$customer_locked_package];
    $order_package_help_text = 'Başvuruda seçtiğiniz pakete göre sipariş tipi otomatik belirlendi.';
} else {
    $available_order_packages = $order_package_catalog;
    $order_package_help_text = 'Bu siparişte oluşturmak istediğiniz kartvizit tipini seçin.';
}

$available_order_packages = [];
if ($customer_locked_package !== '' && isset($order_package_catalog[$customer_locked_package])) {
    $available_order_packages[$customer_locked_package] = [
        'label' => $order_package_catalog[$customer_locked_package]['label'],
        'description' => $order_package_catalog[$customer_locked_package]['description'],
    ];
    $order_package_help_text = 'Hesabınıza tanımlı pakete göre sipariş tipi otomatik belirlendi.';
} else {
    foreach ($order_package_catalog as $package_key => $package_meta) {
        $available_order_packages[$package_key] = [
            'label' => $package_meta['label'],
            'description' => $package_meta['description'],
        ];
    }
    $order_package_help_text = 'Aktif paketinize uygun sipariş tipi burada listelenir.';
}

$selected_package = strtolower(order_old('order_package', array_key_first($available_order_packages) ?: 'smart'));
if (!isset($available_order_packages[$selected_package])) {
    $selected_package = array_key_first($available_order_packages) ?: 'smart';
}

$default_brand_color = normalize_hex_color_for_view((string)($profile['brand_color'] ?? ''), '#0A2F2F');
if (
    $default_brand_color === '#0A2F2F' &&
    !empty($profile['theme_color'])
) {
    $default_brand_color = normalize_hex_color_for_view((string)$profile['theme_color'], '#0A2F2F');
}

$default_qr_style = [
    'color' => $default_brand_color,
    'bg_color' => '#FFFFFF',
    'dot_style' => 'square',
    'corner_style' => 'square',
    'frame_style' => 'classic',
];

$saved_qr_style_payload = trim((string)($profile['qr_style'] ?? ''));
if ($saved_qr_style_payload !== '') {
    $decoded_saved_qr_style = json_decode($saved_qr_style_payload, true);
    if (is_array($decoded_saved_qr_style)) {
        if (!empty($decoded_saved_qr_style['qr_color'])) {
            $default_qr_style['color'] = normalize_hex_color_for_view((string)$decoded_saved_qr_style['qr_color'], $default_qr_style['color']);
        }
        if (!empty($decoded_saved_qr_style['qr_bg_color'])) {
            $default_qr_style['bg_color'] = normalize_hex_color_for_view((string)$decoded_saved_qr_style['qr_bg_color'], $default_qr_style['bg_color']);
        }
        if (!empty($decoded_saved_qr_style['qr_dot_style'])) {
            $default_qr_style['dot_style'] = (string)$decoded_saved_qr_style['qr_dot_style'];
        }
        if (!empty($decoded_saved_qr_style['qr_corner_style'])) {
            $default_qr_style['corner_style'] = (string)$decoded_saved_qr_style['qr_corner_style'];
        }
        if (!empty($decoded_saved_qr_style['qr_frame_style'])) {
            $default_qr_style['frame_style'] = (string)$decoded_saved_qr_style['qr_frame_style'];
        }
    }
}

if (!empty($profile['qr_color'])) {
    $default_qr_style['color'] = normalize_hex_color_for_view((string)$profile['qr_color'], $default_qr_style['color']);
}
if (!empty($profile['qr_bg_color'])) {
    $default_qr_style['bg_color'] = normalize_hex_color_for_view((string)$profile['qr_bg_color'], $default_qr_style['bg_color']);
}
if (!empty($profile['qr_dot_style'])) {
    $default_qr_style['dot_style'] = (string)$profile['qr_dot_style'];
}
if (!empty($profile['qr_corner_style'])) {
    $default_qr_style['corner_style'] = (string)$profile['qr_corner_style'];
}
if (!empty($profile['qr_frame_style'])) {
    $default_qr_style['frame_style'] = (string)$profile['qr_frame_style'];
}

$default_qr_style['color'] = normalize_hex_color_for_view(order_digital_old('color', $default_qr_style['color']), $default_qr_style['color']);
$default_qr_style['bg_color'] = normalize_hex_color_for_view(order_digital_old('bg_color', $default_qr_style['bg_color']), $default_qr_style['bg_color']);
$default_qr_style['dot_style'] = order_digital_old('dot_style', $default_qr_style['dot_style']);
$default_qr_style['corner_style'] = order_digital_old('corner_style', $default_qr_style['corner_style']);
$default_qr_style['frame_style'] = order_digital_old('frame_style', $default_qr_style['frame_style']);

$default_cover_color = normalize_hex_color_for_view((string)($profile['cover_color'] ?? ''), $default_brand_color);
$default_avatar_color = normalize_hex_color_for_view((string)($profile['avatar_color'] ?? ''), $default_brand_color);
$default_display_name = trim((string)($profile['full_name'] ?? $user_name));
$default_bio = trim((string)($profile['bio'] ?? ''));
$default_avatar_initial = normalize_initial_for_view(
    (string)($profile['avatar_initial'] ?? ''),
    derive_initial_from_name_for_view($default_display_name !== '' ? $default_display_name : $user_name, 'A')
);

$default_display_name = order_digital_profile_old('display_name', $default_display_name);
$default_bio = order_digital_profile_old('bio', $default_bio);
$default_cover_color = normalize_hex_color_for_view(order_digital_profile_old('cover_color', $default_cover_color), $default_cover_color);
$default_avatar_color = normalize_hex_color_for_view(order_digital_profile_old('avatar_color', $default_avatar_color), $default_avatar_color);
$default_avatar_initial = normalize_initial_for_view(order_digital_profile_old('avatar_initial', $default_avatar_initial), $default_avatar_initial);

$social_platform_options = qrk_get_social_platform_options();
$known_social_platforms = array_column($social_platform_options, 'value');

$social_links_for_view = [];
if (
    !empty($profile['id']) &&
    table_exists_for_view($pdo, 'social_links')
) {
    $has_logo_path = table_has_column_for_view($pdo, 'social_links', 'logo_path');
    $sql_links = $has_logo_path
        ? 'SELECT platform, url, logo_path FROM social_links WHERE profile_id = ? ORDER BY id ASC'
        : 'SELECT platform, url, NULL AS logo_path FROM social_links WHERE profile_id = ? ORDER BY id ASC';
    $stmt_links = $pdo->prepare($sql_links);
    $stmt_links->execute([(int)$profile['id']]);
    $social_links_for_view = $stmt_links->fetchAll() ?: [];
}

$digital_link_platforms = order_digital_links_old('platforms');
$digital_link_urls = order_digital_links_old('urls');
$digital_link_customs = order_digital_links_old('customs');
$digital_link_existing_logos = order_digital_links_old('existing_logos');

if ($digital_link_platforms === [] && !empty($social_links_for_view)) {
    foreach ($social_links_for_view as $row) {
        $platform_raw = strtolower(trim((string)($row['platform'] ?? '')));
        $is_custom = !in_array($platform_raw, $known_social_platforms, true);
        $digital_link_platforms[] = $is_custom ? '__custom__' : $platform_raw;
        $digital_link_urls[] = (string)($row['url'] ?? '');
        $digital_link_customs[] = $is_custom ? $platform_raw : '';
        $existing_logo = trim((string)($row['logo_path'] ?? ''));
        if (!preg_match('#^assets/uploads/social_logos/[A-Za-z0-9._-]+$#', $existing_logo)) {
            $existing_logo = '';
        }
        $digital_link_existing_logos[] = $existing_logo;
    }
}

if ($digital_link_platforms === []) {
    $digital_link_platforms = ['instagram'];
    $digital_link_urls = [''];
    $digital_link_customs = [''];
    $digital_link_existing_logos = [''];
}

$dynamic_fields = df_get_form_fields($pdo, false);

if (!function_exists('resolve_dynamic_value')) {
    function resolve_dynamic_value(array $field, string $fallback_user_name, array $fallback_profile): string
    {
        $field_key = (string) ($field['field_key'] ?? '');
        $default_value = (string) ($field['default_value'] ?? '');

        if ($field_key === '') {
            return '';
        }

        $old_value = order_dynamic_old($field_key, '__DF_NULL__');
        if ($old_value !== '__DF_NULL__') {
            return $old_value;
        }

        if ($field_key === 'full_name') {
            return $fallback_user_name;
        }
        if ($field_key === 'company_name') {
            return trim((string) ($fallback_profile['company'] ?? ''));
        }
        if ($field_key === 'job_title') {
            return trim((string) ($fallback_profile['title'] ?? ''));
        }

        return $default_value;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sipariş - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .order-form-card {
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            border: 1px solid transparent;
            border-radius: 20px;
            padding: 1.4rem;
            box-shadow: 0 14px 30px rgba(10, 47, 47, 0.05);
        }
        .digital-config-card,
        .digital-subsection,
        .digital-file-upload,
        .social-link-item,
        .qr-config-controls,
        .qr-config-preview {
            transition: border-color 0.24s ease, box-shadow 0.24s ease, background 0.24s ease, transform 0.24s ease;
        }
        .notice-box {
            border-radius: 14px;
            padding: 0.9rem 1rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        .notice-success {
            border: 1px solid #86efac;
            background: #f0fdf4;
            color: #14532d;
        }
        .notice-error {
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: #991b1b;
        }
        .order-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .field label {
            font-size: 0.82rem;
            font-weight: 800;
            color: #475569;
        }
        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid #dbe3ee;
            border-radius: 12px;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(135deg, rgba(219, 227, 238, 0.96), rgba(219, 227, 238, 0.96)) border-box;
            color: #0f172a;
            padding: 0.75rem 0.9rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease, transform 0.22s ease;
        }
        .field textarea {
            min-height: 110px;
            resize: vertical;
        }
        .field input[type="file"] {
            padding: 0.45rem 0.55rem;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            cursor: pointer;
        }
        .field input[type="file"]::file-selector-button {
            border: none;
            border-radius: 16px;
            margin-right: 0.85rem;
            padding: 0.78rem 1.05rem;
            background: linear-gradient(135deg, rgba(10,47,47,0.96), rgba(15,74,74,0.92));
            color: #ffffff;
            font: inherit;
            font-weight: 800;
            letter-spacing: 0.01em;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(10, 47, 47, 0.16);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .field input[type="file"]:hover::file-selector-button,
        .field input[type="file"]:focus::file-selector-button {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(10, 47, 47, 0.22);
            background: linear-gradient(135deg, rgba(12,59,59,0.98), rgba(22,96,96,0.94));
        }
        .file-selection-meta {
            margin-top: 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
            color: #475569;
            font-size: 0.82rem;
        }
        .file-selection-meta.is-empty {
            color: #94a3b8;
        }
        .file-selection-name {
            font-weight: 700;
            word-break: break-word;
        }
        .file-selection-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.38rem 0.7rem;
            border-radius: 999px;
            border: 1px solid #dbe4ef;
            background: #ffffff;
            color: #0f172a;
            text-decoration: none;
            font-weight: 700;
            transition: 0.2s ease;
        }
        .file-selection-link:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            transform: translateY(-1px);
        }
        .field input:hover,
        .field select:hover,
        .field textarea:hover {
            border-color: transparent;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.92) 0%, rgba(214, 180, 106, 0.92) 35%, rgba(255, 244, 214, 0.92) 50%, rgba(214, 180, 106, 0.92) 65%, rgba(247, 233, 191, 0.92) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.6s linear infinite;
            box-shadow: 0 0 0 2px rgba(212, 167, 72, 0.12), 0 8px 18px rgba(166, 128, 63, 0.08);
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: transparent;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.98) 35%, rgba(255, 244, 214, 0.98) 50%, rgba(214, 180, 106, 0.98) 65%, rgba(247, 233, 191, 0.98) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 2.8s linear infinite;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.16), 0 10px 20px rgba(166, 128, 63, 0.10);
            transform: translateY(-1px);
        }
        .field:focus-within label {
            color: #0A2F2F;
        }
        .field-help {
            font-size: 0.76rem;
            color: #64748b;
            font-weight: 600;
        }

        /* QR Preview responsiveness */
        .qr-info-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 220px;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 900px) {
            .qr-info-grid {
                grid-template-columns: 1fr;
            }
            .orderQrPreviewFrame {
                margin: 0 auto;
                width: 100%;
                max-width: 280px;
            }
        }
        .order-package-stack {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }
        .order-package-option {
            display: block;
            border: 1px solid #dbe3ee;
            border-radius: 14px;
            background: #fff;
            padding: 0.95rem 1rem;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, transform 0.2s ease;
        }
        .order-package-option:hover {
            border-color: transparent;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.92) 0%, rgba(214, 180, 106, 0.92) 35%, rgba(255, 244, 214, 0.92) 50%, rgba(214, 180, 106, 0.92) 65%, rgba(247, 233, 191, 0.92) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.6s linear infinite;
            box-shadow: 0 0 0 2px rgba(212, 167, 72, 0.12), 0 10px 20px rgba(166, 128, 63, 0.10);
            transform: translateY(-1px);
        }
        .order-package-option.active {
            border-color: transparent;
            background:
                linear-gradient(#fffdf7, #fffdf7) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.98) 35%, rgba(255, 244, 214, 0.98) 50%, rgba(214, 180, 106, 0.98) 65%, rgba(247, 233, 191, 0.98) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.2s linear infinite;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.16), 0 12px 24px rgba(166, 128, 63, 0.12);
        }
        .order-package-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .order-package-option-title {
            display: block;
            color: #0A2F2F;
            font-size: 0.95rem;
            font-weight: 800;
        }
        .order-package-option-desc {
            display: block;
            margin-top: 0.3rem;
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .full {
            grid-column: 1 / -1;
        }
        .submit-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.2rem;
        }
        .btn-submit {
            border: 1px solid transparent;
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            border-radius: 12px;
            padding: 0.8rem 1.3rem;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 14px 24px rgba(6, 36, 36, 0.24);
        }
        .btn-submit:hover {
            transform: translateY(-1px);
        }
        @keyframes goldFocusFlow {
            0% {
                background-position: 0 0, 0% 50%;
            }
            100% {
                background-position: 0 0, 200% 50%;
            }
        }
        @media (max-width: 900px) {
            .order-form-grid {
                grid-template-columns: 1fr;
            }
            .submit-row {
                justify-content: stretch;
            }
            .btn-submit {
                width: 100%;
                justify-content: center;
            }
            .digital-profile-grid {
                grid-template-columns: 1fr;
            }
            .social-link-item {
                flex-direction: column;
                align-items: stretch;
            }
            .social-link-item select {
                width: 100%;
                min-width: unset;
                margin-bottom: 0.5rem;
            }
            .digital-config-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }
            .order-form-card {
                padding: 1rem;
            }
            .digital-subsection {
                padding: 0.75rem;
            }
        }
        .digital-config-card {
            margin-top: 1.25rem;
            border: 1px solid rgba(224, 231, 240, 0.95);
            border-radius: 28px;
            background: linear-gradient(180deg, #fcfdff 0%, #f7fafc 100%);
            padding: 1.2rem;
            box-shadow: 0 16px 34px rgba(10, 47, 47, 0.05);
            display: none;
        }
        .digital-config-card.visible {
            display: block;
        }
        .digital-config-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.9rem;
        }
        .digital-config-head h4 {
            margin: 0;
            color: #0A2F2F;
            font-size: 1rem;
        }
        .digital-config-head p {
            margin: 0.25rem 0 0;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 600;
        }
        
        .digital-config-note {
            margin-top: 0.3rem;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.4;
        }
        .digital-config-badge {
            border: 1px solid #86efac;
            color: #166534;
            background: #ecfdf5;
            border-radius: 999px;
            font-weight: 800;
            font-size: 0.72rem;
            padding: 0.28rem 0.68rem;
        }
        .digital-subsection {
            border: 1px solid #dbe4ef;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
            padding: 1rem 1.05rem;
            margin-bottom: 1rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.78);
        }
        .digital-subsection:hover,
        .digital-subsection:focus-within,
        .digital-config-card:hover,
        .digital-config-card:focus-within,
        .qr-config-controls:hover,
        .qr-config-controls:focus-within,
        .qr-config-preview:hover,
        .qr-config-preview:focus-within {
            border-color: transparent;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(248,250,252,0.99) 100%) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.96) 0%, rgba(214, 180, 106, 0.92) 35%, rgba(255, 244, 214, 0.96) 50%, rgba(214, 180, 106, 0.92) 65%, rgba(247, 233, 191, 0.96) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.4s linear infinite;
            box-shadow: 0 0 0 2px rgba(212, 167, 72, 0.10), 0 16px 32px rgba(166, 128, 63, 0.10);
        }
        .digital-subsection-title {
            margin: 0 0 0.75rem;
            color: #0A2F2F;
            font-size: 0.93rem;
            font-weight: 800;
        }
        .digital-profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.9rem;
        }
        .digital-file-upload {
            border: 1px dashed #cbd5e1;
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
            padding: 0.9rem 1rem;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.82);
        }
        .digital-file-upload .field-help {
            margin-top: 0.4rem;
            display: block;
        }
        .digital-file-upload:hover,
        .digital-file-upload:focus-within {
            border-color: transparent;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(248,250,252,0.99) 100%) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.96) 0%, rgba(214, 180, 106, 0.92) 35%, rgba(255, 244, 214, 0.96) 50%, rgba(214, 180, 106, 0.92) 65%, rgba(247, 233, 191, 0.96) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.2s linear infinite;
            box-shadow: 0 0 0 2px rgba(212, 167, 72, 0.12), 0 14px 30px rgba(166, 128, 63, 0.08);
        }
        .digital-file-upload input[type="file"] {
            width: 100%;
            border: 1px solid #dbe3ee;
            border-radius: 20px;
            background: #ffffff;
            color: #0f172a;
            padding: 0.45rem 0.55rem;
            font: inherit;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
        }
        .digital-file-upload input[type="file"]:hover,
        .digital-file-upload input[type="file"]:focus {
            outline: none;
            border-color: #e0b95b;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.12);
            background: #fffdf8;
        }
        .digital-file-upload input[type="file"]::file-selector-button {
            border: none;
            border-radius: 16px;
            margin-right: 0.8rem;
            padding: 0.72rem 1rem;
            background: linear-gradient(135deg, rgba(10,47,47,0.96), rgba(15,74,74,0.92));
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 10px 18px rgba(10, 47, 47, 0.16);
        }
        .digital-file-upload input[type="file"]:hover::file-selector-button {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(10, 47, 47, 0.22);
            background: linear-gradient(135deg, rgba(12,59,59,0.98), rgba(22,96,96,0.94));
        }
        .social-link-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 0.95rem 1rem;
            margin-bottom: 0.85rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .social-link-item:hover,
        .social-link-item:focus-within {
            border-color: transparent;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(248,250,252,0.99) 100%) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.96) 0%, rgba(214, 180, 106, 0.92) 35%, rgba(255, 244, 214, 0.96) 50%, rgba(214, 180, 106, 0.92) 65%, rgba(247, 233, 191, 0.96) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3.2s linear infinite;
            box-shadow: 0 0 0 2px rgba(212, 167, 72, 0.10), 0 18px 30px rgba(166, 128, 63, 0.10);
        }
        .social-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 18px;
            border: 1px solid #e4d7b0;
            background: linear-gradient(135deg, #fffdfa 0%, #f8f1df 100%);
            color: #0A2F2F;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.88);
        }
        .social-brand-logo { width: 24px; height: 24px; object-fit: contain; display: block; }
        .social-link-content {
            flex: 1;
            min-width: 0;
        }
        .social-link-content input,
        .social-link-item select,
        .platform-logo-row input[type="file"],
        .color-input-row input[type="text"] {
            width: 100%;
            border: 1px solid #dbe3ee;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            color: #0f172a;
            padding: 0.82rem 0.95rem;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease, transform 0.22s ease;
        }
        .social-link-content input:hover,
        .social-link-item select:hover,
        .platform-logo-row input[type="file"]:hover,
        .color-input-row input[type="text"]:hover,
        .social-link-content input:focus,
        .social-link-item select:focus,
        .platform-logo-row input[type="file"]:focus,
        .color-input-row input[type="text"]:focus {
            outline: none;
            border-color: transparent;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.95) 35%, rgba(255, 244, 214, 0.98) 50%, rgba(214, 180, 106, 0.95) 65%, rgba(247, 233, 191, 0.98) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3s linear infinite;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.12), 0 10px 20px rgba(166, 128, 63, 0.10);
            transform: translateY(-1px);
        }
        .social-link-item select {
            width: 170px;
            min-width: 170px;
        }
        .platform-logo-row {
            margin-top: 0.65rem;
            display: none;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }
        .platform-logo-row input[type="file"] {
            max-width: 250px;
        }
        .platform-logo-row input[type="file"]::file-selector-button {
            border: none;
            border-radius: 14px;
            margin-right: 0.75rem;
            padding: 0.68rem 0.95rem;
            background: linear-gradient(135deg, rgba(10,47,47,0.96), rgba(15,74,74,0.92));
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .platform-logo-preview {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid #dbe3ee;
            background: #fff;
            object-fit: contain;
            padding: 0.25rem;
        }
        .platform-logo-hint {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
        }
        .btn-remove-social {
            width: 42px;
            height: 42px;
            border-radius: 16px;
            border: 1px solid #fecaca;
            background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
            color: #dc2626;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease, background 0.22s ease;
        }
        .btn-remove-social:hover {
            border-color: #fca5a5;
            box-shadow: 0 10px 18px rgba(239, 68, 68, 0.14);
            transform: translateY(-1px);
        }
        .btn-add-social {
            width: 100%;
            border: 1px solid #dbe3ee;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            color: #0A2F2F;
            border-radius: 22px;
            padding: 0.92rem 1rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
            transition: border-color 0.22s ease, box-shadow 0.22s ease, background 0.22s ease, transform 0.22s ease;
        }
        .btn-add-social:hover {
            border-color: transparent;
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(120deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.95) 35%, rgba(255, 244, 214, 0.98) 50%, rgba(214, 180, 106, 0.95) 65%, rgba(247, 233, 191, 0.98) 100%) border-box;
            background-size: 100% 100%, 220% 220%;
            animation: goldFocusFlow 3s linear infinite;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.10), 0 14px 26px rgba(166, 128, 63, 0.10);
            transform: translateY(-1px);
        }
        .qr-config-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 1rem;
        }
        .qr-config-controls, .qr-config-preview {
            border: 1px solid #dbe4ef;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
            padding: 1rem;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
        }
        .qr-config-controls .field {
            margin-bottom: 0.75rem;
        }
        .qr-config-controls .field:last-child {
            margin-bottom: 0;
        }
        .color-input-row {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }
        .color-input-row input[type="color"] {
            width: 54px;
            min-width: 54px;
            height: 54px;
            border: 1px solid #dbe4ef;
            border-radius: 18px;
            padding: 0.25rem;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.22s ease;
        }
        .color-input-row input[type="color"]:hover,
        .color-input-row input[type="color"]:focus {
            outline: none;
            border-color: #e0b95b;
            box-shadow: 0 0 0 3px rgba(212, 167, 72, 0.12), 0 12px 20px rgba(166, 128, 63, 0.10);
            transform: translateY(-1px);
        }
        .color-input-row input[type="text"] {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        .qr-preview-frame {
            width: 220px;
            height: 220px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: 0.2s ease;
        }
        .qr-preview-frame.frame-classic {
            border-radius: 14px;
            border: 1px solid #dbe4ef;
            box-shadow: 0 8px 24px rgba(2, 28, 43, 0.06);
        }
        .qr-preview-frame.frame-soft {
            border-radius: 24px;
            border: 1px solid #dbe4ef;
            box-shadow: 0 12px 30px rgba(2, 28, 43, 0.08);
            background: linear-gradient(145deg, #ffffff, #f1f5f9);
        }
        .qr-preview-frame.frame-badge {
            width: 236px;
            height: 236px;
            border-radius: 999px;
            border: 10px solid #fff;
            box-shadow: 0 12px 30px rgba(2, 28, 43, 0.12);
        }
        .qr-preview-frame.frame-none {
            border: none;
            box-shadow: none;
            background: transparent;
        }
        .qr-preview-canvas {
            width: 186px;
            height: 186px;
        }
        .qr-preview-note {
            margin-top: 0.65rem;
            text-align: center;
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 600;
        }
        /* ── Renk Seçici (color_preferences) Premium ───────────── */
        .color-selector-container {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.5rem;
            margin-top: 0.5rem;
        }
        .color-module {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .color-module:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(166, 128, 63, 0.08);
        }
        .color-module-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }
        .color-module-info {
            min-width: 0;
        }
        .color-module-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(166, 128, 63, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
        }
        .color-module-info b {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--navy-blue);
        }
        .color-module-info span {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .cp-preset-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.6rem;
            margin-bottom: 1.25rem;
        }
        .cp-preset-btn {
            aspect-ratio: 1;
            border-radius: 10px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05);
        }
        .cp-preset-btn:hover {
            transform: scale(1.1);
            z-index: 2;
        }
        .cp-preset-btn.active {
            border-color: var(--gold);
            transform: scale(1.05);
            box-shadow: 0 0 0 4px rgba(166, 128, 63, 0.15);
        }
        .cp-preset-btn.active::after {
            content: '';
            position: absolute;
            top: -4px;
            right: -4px;
            width: 12px;
            height: 12px;
            background: var(--gold);
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .cp-custom-input-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .cp-mini-picker-wrap {
            position: relative;
            width: 58px;
            height: 58px;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid #fff;
            flex-shrink: 0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.22s ease;
            outline: 1px solid #dbe4ef;
        }
        .cp-mini-picker-wrap:hover {
            transform: scale(1.05);
            outline-color: var(--gold);
            box-shadow: 0 12px 30px rgba(166, 128, 63, 0.2);
        }
        .cp-mini-picker-wrap input[type="color"] {
            position: absolute;
            inset: -5px;
            width: 120%;
            height: 120%;
            cursor: pointer;
            border: 0;
            padding: 0;
        }
        .cp-studio-box {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.85rem;
            margin-top: 0.85rem;
            padding: 0.95rem;
            background: #f8fafc;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
        }
        .cp-hex-box {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }
        .cp-hint {
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: normal;
        }
        .cp-hex-input {
            margin: 0 !important;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            font-weight: 800 !important;
            text-align: left;
            height: 42px !important;
            border-radius: 12px !important;
            font-size: 0.96rem !important;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding-left: 0.95rem !important;
            border-color: #dbe4ef !important;
            width: 100% !important;
            min-width: 0;
        }
        @media (max-width: 1320px) {
            .color-selector-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 1080px) {
            .color-selector-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .color-selector-container {
                grid-template-columns: 1fr;
            }
        }
        /* ── QR Studio ──────────────────────────────────── */
        .qr-studio-grid {
            display: grid;
            grid-template-columns: 1fr 250px;
            gap: 1.5rem;
            align-items: start;
        }
        .qr-studio-controls {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }
        .qr-group {
            background: rgba(248,250,252,0.8);
            border: 1px solid #e8edf5;
            border-radius: 16px;
            padding: 0.9rem 1rem;
        }
        .qr-group-label {
            font-size: 0.69rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 0.7rem;
        }
        .qr-colors-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        .qr-style-cards {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }
        .qr-style-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.32rem;
            padding: 0.6rem 0.4rem 0.5rem;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            cursor: pointer;
            transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s, background 0.18s;
            flex: 1;
            min-width: 52px;
            font-size: 0.69rem;
            font-weight: 700;
            color: #64748b;
            font-family: inherit;
            line-height: 1;
        }
        .qr-style-card:hover {
            border-color: #c7d2e4;
            box-shadow: 0 4px 12px rgba(15,23,42,0.07);
            transform: translateY(-1px);
        }
        .qr-style-card.selected {
            border-color: #1d4ed8;
            background: #eff6ff;
            color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29,78,216,0.10);
        }
        .qr-dot-mini {
            display: grid;
            grid-template-columns: repeat(4, 6px);
            grid-template-rows: repeat(4, 6px);
            gap: 2px;
            color: #475569;
        }
        .qr-style-card.selected .qr-dot-mini { color: #1d4ed8; }
        .qr-dot-mini span {
            display: block;
            width: 6px;
            height: 6px;
            background: currentColor;
        }
        .qr-corner-mini {
            width: 28px;
            height: 28px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
        }
        .qr-style-card.selected .qr-corner-mini { color: #1d4ed8; }
        .qr-corner-mini .cm-outer {
            position: absolute;
            inset: 0;
            border: 4px solid currentColor;
        }
        .qr-corner-mini .cm-inner {
            width: 10px;
            height: 10px;
            background: currentColor;
            position: relative;
            z-index: 1;
        }
        .qr-frame-mini {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
        }
        .qr-style-card.selected .qr-frame-mini { color: #1d4ed8; }
        .qr-frame-mini-inner {
            width: 14px;
            height: 14px;
            background: currentColor;
            opacity: 0.5;
        }
        .qr-studio-preview {
            position: sticky;
            top: 1.5rem;
        }
        .qr-preview-wrap {
            background: linear-gradient(145deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 1.4rem 1.1rem;
            text-align: center;
            box-shadow: 0 8px 28px rgba(15,23,42,0.07);
        }
        .qr-preview-label-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 1.1rem;
        }
        .qr-live-dot {
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            flex-shrink: 0;
            animation: qrPulse 2s ease-in-out infinite;
        }
        @keyframes qrPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        .qr-preview-note-sub {
            font-size: 0.66rem;
            color: #cbd5e1;
            margin-top: 0.6rem;
            line-height: 1.4;
        }
        @media (max-width: 900px) {
            .order-form-grid {
                grid-template-columns: 1fr !important;
            }
            .digital-profile-grid {
                grid-template-columns: 1fr;
            }
            .social-link-item {
                flex-direction: column;
            }
            .social-link-item select {
                width: 100%;
                min-width: 0;
            }
            .qr-config-grid {
                grid-template-columns: 1fr;
            }
            .qr-preview-frame {
                width: 100%;
                max-width: 260px;
                height: 240px;
            }
            .qr-preview-frame.frame-badge {
                width: 220px;
                height: 220px;
            }
            .qr-studio-grid {
                grid-template-columns: 1fr;
            }
            .qr-studio-preview {
                position: static;
            }
            .qr-style-card {
                min-width: 46px;
            }
        }
        @media (max-width: 360px) {
            .main-content { padding: 0.5rem; }
            .order-form-card { padding: 0.85rem; border-radius: 16px; }
            .digital-subsection { padding: 0.65rem; }
            input, select, textarea { font-size: 16px !important; }
        }
    </style>
</head>
<body class="dashboard-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>
                <li class="active"><a href="new-order.php"><i data-lucide="plus-circle"></i> Yeni Kartvizit Siparişi</a></li>
                <li><a href="profile.php"><i data-lucide="user-cog"></i> Dijital Profilim</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="details">
                    <span class="name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="role">Müşteri</span>
                </div>
            </div>
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Yeni Sipariş Oluştur</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Baskılı kartvizit, dijital kartvizit veya ikisini birden buradan sipariş verebilirsiniz.</p>
            </div>
            <a href="orders.php" style="background:#fff; border:1px solid #e2e8f0; color:#0A2F2F; text-decoration:none; border-radius:12px; padding:0.7rem 1rem; font-weight:800;">
                Siparişlerim
            </a>
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; justify-content:flex-end;">
                <div style="background:#fff; border:1px solid #e2e8f0; color:#0A2F2F; border-radius:12px; padding:0.7rem 1rem; font-weight:800;">
                    <?php echo $is_preview_mode ? 'İnceleme Paketi' : 'Aktif Paket'; ?>: <?php echo htmlspecialchars((string)$current_package_definition['label']); ?>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($notice_message !== ''): ?>
                <div class="notice-box notice-success"><i data-lucide="check-circle" style="width:18px; margin-right:0.35rem; vertical-align:middle;"></i><?php echo htmlspecialchars($notice_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message !== ''): ?>
                <div class="notice-box notice-error"><i data-lucide="alert-circle" style="width:18px; margin-right:0.35rem; vertical-align:middle;"></i><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($is_preview_mode): ?>
                <div class="notice-box" style="background:linear-gradient(135deg, #eff6ff, #ffffff); border:1px solid #bfdbfe; color:#1d4ed8;">
                    <i data-lucide="sparkles" style="width:18px; margin-right:0.35rem; vertical-align:middle;"></i>
                    İnceleme modundasınız. Formu doldurabilir ve tüm akışı görebilirsiniz. “Siparişi Oluştur” dediğiniz anda sistem sizi satın alma hazırlığı ekranına yönlendirecek.
                </div>
            <?php endif; ?>

            <?php if (!$can_create_order): ?>
                <section class="order-form-card" id="new-order">
                    <?php $order_lock_reason = (string)($package_state['lock_reason'] ?? ''); ?>
                    <?php $pending_package_slug = (string)($package_state['pending_package_slug'] ?? ''); ?>
                    <h3 style="margin: 0; color: #0A2F2F;">Aktif Paket Bulunamadı</h3>
                    <p style="margin: 0.45rem 0 0; color: #64748b; font-size: 0.95rem;">
                        <?php if ($pending_package_slug !== ''): ?>
                            Paket seçiminiz kayıt altında; ancak henüz aktifleştirilmedi. Sipariş oluşturabilmek için satın alma akışının tamamlanması gerekir.
                        <?php else: ?>
                            Sipariş oluşturabilmeniz için hesabınıza bir paket tanımlanmış olmalı. Lütfen yönetici ile iletişime geçin.
                        <?php endif; ?>
                    </p>

                    <div style="display:grid; gap:1rem; margin-top:1.4rem;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:18px; padding:1rem 1.1rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                <div>
                                    <div style="font-size:0.8rem; color:#64748b; font-weight:700;">Tanımlı Paket</div>
                                    <div style="font-size:1.05rem; font-weight:800; color:#0A2F2F;"><?php echo htmlspecialchars((string)$current_package_definition['label']); ?></div>
                                </div>
                                <div style="font-size:0.86rem; color:#475569; font-weight:700;">Durum: Paket tanımı bekleniyor</div>
                            </div>
                            <p style="margin:0.8rem 0 0; color:#64748b; line-height:1.6;"><?php echo htmlspecialchars((string)$current_package_definition['description']); ?></p>
                        </div>

                        <?php if (!empty($current_package_definition['included_features'])): ?>
                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1rem 1.1rem;">
                                <div style="font-size:0.82rem; color:#0A2F2F; font-weight:800; margin-bottom:0.65rem;">Bu Pakette Aktif</div>
                                <div style="display:flex; flex-wrap:wrap; gap:0.55rem;">
                                    <?php foreach (($current_package_definition['included_features'] ?? []) as $feature): ?>
                                        <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 0.8rem; border-radius:999px; background:#ecfdf5; color:#166534; font-weight:700; font-size:0.82rem;">
                                            <i data-lucide="check" style="width:14px;"></i><?php echo htmlspecialchars((string)$feature); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($current_package_definition['excluded_features'])): ?>
                            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:18px; padding:1rem 1.1rem;">
                                <div style="font-size:0.82rem; color:#7c2d12; font-weight:800; margin-bottom:0.65rem;">Bu Pakette Kapalı</div>
                                <div style="display:flex; flex-wrap:wrap; gap:0.55rem;">
                                    <?php foreach (($current_package_definition['excluded_features'] ?? []) as $feature): ?>
                                        <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 0.8rem; border-radius:999px; background:#fff7ed; color:#9a3412; font-weight:700; font-size:0.82rem;">
                                            <i data-lucide="minus" style="width:14px;"></i><?php echo htmlspecialchars((string)$feature); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                            <?php if ($pending_package_slug !== ''): ?>
                                <a href="purchase-review.php" class="btn-action btn-primary-action" style="margin:0;">
                                    <i data-lucide="wallet"></i> Satın Alma Hazırlığı
                                </a>
                            <?php endif; ?>

                            <?php if ($order_lock_reason === 'order_limit_reached'): ?>
                                <a href="profile.php" class="btn-action" style="margin:0;">
                                    <i data-lucide="user-cog"></i> Web Sitesini Güncelle
                                </a>
                            <?php endif; ?>
                            <a href="orders.php" class="btn-action" style="margin:0;">
                                <i data-lucide="shopping-bag"></i> Siparişlerime Git
                            </a>
                            <a href="design-tracking.php" class="btn-action" style="margin:0;">
                                <i data-lucide="palette"></i> Tasarım Sürecini Aç
                            </a>
                        </div>
                    </div>
                </section>
            <?php else: ?>
            <section class="order-form-card" id="new-order">
                <h3 style="margin: 0; color: #0A2F2F;">Sipariş Formu</h3>
                <p style="margin: 0.35rem 0 0; color: #64748b; font-size: 0.92rem;"><?php echo $is_preview_mode ? 'İnceleme modunda formu serbestçe deneyebilirsiniz. Gönderim anında satın alma hazırlığı ekranına geçersiniz.' : 'Aktif alanlar tasarımcı panelinden yönetilir. Pasif alanlar sistemde varsayılan değer ile tutulur.'; ?></p>

                <form action="../processes/customer_order_create.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>

                    <div class="order-form-grid">
                        <div class="field full">
                            <label>Sipariş Tipi</label>
                            <div class="order-package-stack">
                                <?php foreach ($available_order_packages as $package_key => $package_meta): ?>
                                    <label class="order-package-option <?php echo $selected_package === $package_key ? 'active' : ''; ?>" data-order-package-option>
                                        <input
                                            id="order-package-<?php echo htmlspecialchars($package_key, ENT_QUOTES, 'UTF-8'); ?>"
                                            type="radio"
                                            name="order_package"
                                            value="<?php echo htmlspecialchars($package_key, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo $selected_package === $package_key ? 'checked' : ''; ?>
                                        >
                                        <span class="order-package-option-title"><?php echo htmlspecialchars((string) $package_meta['label']); ?></span>
                                        <span class="order-package-option-desc"><?php echo htmlspecialchars((string) $package_meta['description']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <span class="field-help"><?php echo htmlspecialchars($order_package_help_text); ?></span>
                        </div>

                        <div class="field">
                            <label for="logo">Kurumsal Logo (Opsiyonel)</label>
                            <input id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                            <span class="field-help">JPG, PNG veya WEBP - en fazla 5 MB.</span>
                        </div>

                        <div class="field full" style="gap:0.75rem;">
                            <div style="padding:1rem 1.1rem; border-radius:18px; border:1px solid #dbe4ef; background:linear-gradient(180deg, #fffdf7 0%, #f8fafc 100%); color:#475569; line-height:1.7;">
                                Baskılı kartvizit siparişlerinde ebat, yön, yüz sayısı, kağıt, selefon, renk ve kart üzerinde yer alacak bilgiler net istenir.
                                Bu alanları ne kadar açık doldurursanız tasarım ve baskı süreci o kadar hızlı ve hatasız ilerler.
                            </div>
                        </div>

                        <?php foreach ($dynamic_fields as $field): ?>
                            <?php
                                $field_key = (string) ($field['field_key'] ?? '');
                                $field_type = strtolower((string) ($field['field_type'] ?? 'text'));
                                $field_label = (string) ($field['field_label'] ?? $field_key);
                                $placeholder = (string) ($field['placeholder'] ?? '');
                                $help_text = (string) ($field['help_text'] ?? '');
                                $show_packages = (string) ($field['show_on_packages'] ?? '');
                                $required_packages = (string) ($field['required_on_packages'] ?? '');
                                $is_required = df_field_is_required_for_package($field, $selected_package);
                                $is_visible = df_field_is_visible_for_package($field, $selected_package);
                                $is_full = $field_type === 'textarea';
                                $resolved_value = resolve_dynamic_value($field, $user_name, $profile);
                            ?>
                            <div
                                class="field <?php echo $is_full ? 'full' : ''; ?>"
                                data-df-field="<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>"
                                data-show-packages="<?php echo htmlspecialchars($show_packages, ENT_QUOTES, 'UTF-8'); ?>"
                                data-required-packages="<?php echo htmlspecialchars($required_packages, ENT_QUOTES, 'UTF-8'); ?>"
                                data-base-required="<?php echo (int) ($field['is_required'] ?? 0); ?>"
                                <?php echo $is_visible ? '' : 'style="display:none;"'; ?>
                            >
                                <label for="df-<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($field_label); ?>
                                    <?php if ($is_required): ?>
                                        <span style="color:#dc2626;">*</span>
                                    <?php endif; ?>
                                </label>

                                <?php if ($field_type === 'textarea'): ?>
                                    <textarea
                                        id="df-<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>"
                                        name="dynamic_fields[<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>]"
                                        placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                                        <?php echo $is_required ? 'required' : ''; ?>
                                    ><?php echo htmlspecialchars($resolved_value); ?></textarea>
                                <?php elseif ($field_type === 'select' && $field_key === 'color_preferences'): ?>
                                    <?php
                                        $cp_options = $field['options'] ?? [];
                                        $cp_default = $resolved_value !== '' ? $resolved_value : (string)($field['default_value'] ?? '');
                                        if ($cp_default === '' && !empty($cp_options)) {
                                            $cp_default = (string)($cp_options[0]['option_value'] ?? '');
                                        }
                                        if ($cp_default === '') { $cp_default = '#0F2747'; }
                                        $cp_fk = htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8');

                                        // Mevcut değeri parçalamaya çalışalım (eğer önceden kaydedilmişse)
                                        // Format: "Paper: #xxx, Text: #yyy"
                                        $paper_color = '#0F2747';
                                        $text_color = '#FFFFFF';
                                        $pattern_color = '#A6803F';
                                        if (preg_match('/Paper:\s*(#[0-9a-fA-F]{6});\s*Text:\s*(#[0-9a-fA-F]{6});\s*Pattern:\s*(#[0-9a-fA-F]{6})/', $cp_default, $matches)) {
                                            $paper_color = $matches[1];
                                            $text_color = $matches[2];
                                            $pattern_color = $matches[3];
                                        } elseif (preg_match('/Paper:\s*(#[0-9a-fA-F]{6});\s*Text:\s*(#[0-9a-fA-F]{6})/', $cp_default, $matches)) {
                                            $paper_color = $matches[1];
                                            $text_color = $matches[2];
                                        } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $cp_default)) {
                                            $paper_color = $cp_default;
                                        }
                                        $cp_hidden_value = sprintf('Paper: %s; Text: %s; Pattern: %s', strtoupper($paper_color), strtoupper($text_color), strtoupper($pattern_color));

                                        // Marka paletini çekelim (varsa)
                                        $brand_palette = [];
                                        if (!empty($profile['brand_palette'])) {
                                            $decoded_palette = json_decode($profile['brand_palette'], true);
                                            if (is_array($decoded_palette)) {
                                                foreach ($decoded_palette as $color) {
                                                    $color = strtoupper(trim((string)$color));
                                                    if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
                                                        $brand_palette[] = $color;
                                                    }
                                                }
                                            }
                                        }

                                        $preset_paper_base = ['#0F2747', '#1e293b', '#FFFFFF', '#f1f5f9', '#A6803F', '#064e3b', '#450a0a', '#1e1b4b', '#7c2d12', '#111827'];
                                        $preset_text_base = ['#FFFFFF', '#F7E9BF', '#0F2747', '#D1D5DB', '#A6803F', '#000000', '#FDE047', '#60A5FA', '#FCA5A5', '#34D399'];

                                        // Marka renklerini en başa ekleyelim (tekrar etmeyecek şekilde)
                                        $preset_paper = array_values(array_unique(array_merge($brand_palette, $preset_paper_base)));
                                        $preset_text = array_values(array_unique(array_merge($brand_palette, $preset_text_base)));

                                        // Maksimum 10 renk gösterelim ki tasarım bozulmasın
                                        $preset_paper = array_slice($preset_paper, 0, 10);
                                        $preset_text = array_slice($preset_text, 0, 10);
                                    ?>
                                    <input type="hidden" id="df-<?php echo $cp_fk; ?>" name="dynamic_fields[<?php echo $cp_fk; ?>]" value="<?php echo htmlspecialchars($cp_hidden_value); ?>">

                                    <div class="color-selector-container">
                                        <!-- Kağıt Rengi -->
                                        <div class="color-module">
                                            <div class="color-module-header">
                                                <div class="color-module-icon"><i data-lucide="palette" style="width:18px;"></i></div>
                                                <div class="color-module-info">
                                                    <b>Ana Kağıt Rengi</b>
                                                    <span>Kartın zemin tonu</span>
                                                </div>
                                            </div>
                                            <div class="cp-studio-box">
                                                <div class="cp-mini-picker-wrap">
                                                    <input type="color" value="<?php echo $paper_color; ?>" id="df-<?php echo $cp_fk; ?>-paper-picker"
                                                        oninput="updateBaskiColor('paper', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                </div>
                                                <div class="cp-hex-box">
                                                    <input type="text" class="cp-hex-input" id="df-<?php echo $cp_fk; ?>-paper-hex" value="<?php echo strtoupper($paper_color); ?>"
                                                        maxlength="7" placeholder="#000000" oninput="updateBaskiColor('paper', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                    <span class="cp-hint">Renk tonunu koda veya palete göre seçin</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Yazı/Logo Rengi -->
                                        <div class="color-module">
                                            <div class="color-module-header">
                                                <div class="color-module-icon"><i data-lucide="type" style="width:18px;"></i></div>
                                                <div class="color-module-info">
                                                    <b>Yazı / Logo Rengi</b>
                                                    <span>Baskı tonu</span>
                                                </div>
                                            </div>
                                            <div class="cp-studio-box">
                                                <div class="cp-mini-picker-wrap">
                                                    <input type="color" value="<?php echo $text_color; ?>" id="df-<?php echo $cp_fk; ?>-text-picker"
                                                        oninput="updateBaskiColor('text', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                </div>
                                                <div class="cp-hex-box">
                                                    <input type="text" class="cp-hex-input" id="df-<?php echo $cp_fk; ?>-text-hex" value="<?php echo strtoupper($text_color); ?>"
                                                        maxlength="7" placeholder="#FFFFFF" oninput="updateBaskiColor('text', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                    <span class="cp-hint">Baskı rengini panel üzerinden belirleyin</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="color-module">
                                            <div class="color-module-header">
                                                <div class="color-module-icon"><i data-lucide="blend" style="width:18px;"></i></div>
                                                <div class="color-module-info">
                                                    <b>Desen Rengi</b>
                                                    <span>Desen ve vurgu tonu</span>
                                                </div>
                                            </div>
                                            <div class="cp-studio-box">
                                                <div class="cp-mini-picker-wrap">
                                                    <input type="color" value="<?php echo $pattern_color; ?>" id="df-<?php echo $cp_fk; ?>-pattern-picker"
                                                        oninput="updateBaskiColor('pattern', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                </div>
                                                <div class="cp-hex-box">
                                                    <input type="text" class="cp-hex-input" id="df-<?php echo $cp_fk; ?>-pattern-hex" value="<?php echo strtoupper($pattern_color); ?>"
                                                        maxlength="7" placeholder="#A6803F" oninput="updateBaskiColor('pattern', this.value, 'df-<?php echo $cp_fk; ?>')">
                                                    <span class="cp-hint">Desen ve detay alanlarında kullanılacak tonu seçin</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($field_type === 'select'): ?>
                                    <select
                                        id="df-<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>"
                                        name="dynamic_fields[<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>]"
                                        <?php echo $is_required ? 'required' : ''; ?>
                                    >
                                        <option value="">Seçiniz</option>
                                        <?php foreach (($field['options'] ?? []) as $option): ?>
                                            <?php
                                                $opt_value = (string) ($option['option_value'] ?? '');
                                                $opt_label = (string) ($option['option_label'] ?? $opt_value);
                                                $selected = $resolved_value !== '' && $resolved_value === $opt_value;
                                            ?>
                                            <option value="<?php echo htmlspecialchars($opt_value); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input
                                        id="df-<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>"
                                        type="<?php echo htmlspecialchars(in_array($field_type, ['email', 'url', 'tel', 'number'], true) ? $field_type : 'text'); ?>"
                                        name="dynamic_fields[<?php echo htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8'); ?>]"
                                        value="<?php echo htmlspecialchars($resolved_value); ?>"
                                        placeholder="<?php echo htmlspecialchars($placeholder); ?>"
                                        <?php echo $is_required ? 'required' : ''; ?>
                                    >
                                <?php endif; ?>

                                <?php if ($help_text !== ''): ?>
                                    <span class="field-help"><?php echo htmlspecialchars($help_text); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <section class="order-form-card" style="margin-top: 1.5rem;">
                        <h3 style="display:flex; align-items:center; gap:0.5rem; font-size:1rem; font-weight:800; color:#0f172a; margin:0 0 0.35rem; letter-spacing:-0.01em;">
                            <i data-lucide="qr-code" style="width:17px;height:17px;flex-shrink:0;"></i> QR Kod Özelleştirme
                        </h3>
                        <p class="field-help" style="margin-bottom: 1.4rem;">Basılı kartınızdaki ve dijital profilinizdeki QR kodun görünümünü kişiselleştirin. Sağdaki önizlemede anlık olarak görün.</p>

                        <div class="qr-studio-grid">

                            <!-- ── Kontroller ── -->
                            <div class="qr-studio-controls">

                                <!-- Renkler -->
                                <div class="qr-group">
                                    <div class="qr-group-label">Renkler</div>
                                    <div class="qr-colors-row">
                                        <div class="field" style="gap:0.35rem;">
                                            <label>QR Rengi</label>
                                            <div class="color-input-row">
                                                <input type="color" name="digital_qr[color]" id="qr-color-input"
                                                    value="<?php echo htmlspecialchars($default_qr_style['color']); ?>"
                                                    oninput="updateQrPreview()">
                                                <input type="text" id="qr-color-text"
                                                    value="<?php echo htmlspecialchars($default_qr_style['color']); ?>"
                                                    style="font-family:monospace;text-transform:uppercase;"
                                                    oninput="syncQrColor(this)">
                                            </div>
                                        </div>
                                        <div class="field" style="gap:0.35rem;">
                                            <label>Arka Plan</label>
                                            <div class="color-input-row">
                                                <input type="color" name="digital_qr[bg_color]" id="qr-bg-input"
                                                    value="<?php echo htmlspecialchars($default_qr_style['bg_color']); ?>"
                                                    oninput="updateQrPreview()">
                                                <input type="text" id="qr-bg-text"
                                                    value="<?php echo htmlspecialchars($default_qr_style['bg_color']); ?>"
                                                    style="font-family:monospace;text-transform:uppercase;"
                                                    oninput="syncQrBg(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Nokta Stili -->
                                <div class="qr-group">
                                    <div class="qr-group-label">Nokta Stili</div>
                                    <div class="qr-style-cards">
                                        <?php
                                        $qr_dot_styles = [
                                            ['value'=>'square',         'label'=>'Kare',     'br'=>'0'],
                                            ['value'=>'dots',           'label'=>'Yuvarlak',    'br'=>'50%'],
                                            ['value'=>'rounded',        'label'=>'Yumuşak Köşe','br'=>'30%'],
                                            ['value'=>'extra-rounded',  'label'=>'Oval',        'br'=>'45%'],
                                            ['value'=>'classy',         'label'=>'Klasik',      'br'=>'0 35% 0 0'],
                                            ['value'=>'classy-rounded', 'label'=>'Şık',         'br'=>'30% 50% 30% 0'],
                                        ];
                                        foreach ($qr_dot_styles as $ds):
                                            $ds_sel = ($default_qr_style['dot_style'] === $ds['value']);
                                        ?>
                                        <button type="button"
                                            class="qr-style-card <?php echo $ds_sel ? 'selected' : ''; ?>"
                                            data-value="<?php echo htmlspecialchars($ds['value']); ?>"
                                            onclick="selectQrStyle(this,'qr-dot-style')"
                                            title="<?php echo htmlspecialchars($ds['label']); ?>">
                                            <div class="qr-dot-mini">
                                                <?php for($qi=0;$qi<16;$qi++): ?>
                                                <span style="border-radius:<?php echo $ds['br']; ?>;"></span>
                                                <?php endfor; ?>
                                            </div>
                                            <?php echo htmlspecialchars($ds['label']); ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="digital_qr[dot_style]" id="qr-dot-style"
                                        value="<?php echo htmlspecialchars($default_qr_style['dot_style']); ?>">
                                </div>

                                <!-- Köşe Stili -->
                                <div class="qr-group">
                                    <div class="qr-group-label">Köşe Stili</div>
                                    <div class="qr-style-cards">
                                        <?php
                                        $qr_corner_styles = [
                                            ['value'=>'square',        'label'=>'Kare',     'outer_br'=>'0',   'inner_br'=>'0'],
                                            ['value'=>'extra-rounded', 'label'=>'Yuvarlak', 'outer_br'=>'30%', 'inner_br'=>'20%'],
                                            ['value'=>'dot',           'label'=>'Nokta',    'outer_br'=>'0',   'inner_br'=>'50%'],
                                        ];
                                        foreach ($qr_corner_styles as $cs):
                                            $cs_sel = ($default_qr_style['corner_style'] === $cs['value']);
                                        ?>
                                        <button type="button"
                                            class="qr-style-card <?php echo $cs_sel ? 'selected' : ''; ?>"
                                            data-value="<?php echo htmlspecialchars($cs['value']); ?>"
                                            onclick="selectQrStyle(this,'qr-corner-style')"
                                            title="<?php echo htmlspecialchars($cs['label']); ?>"
                                            style="max-width:110px;">
                                            <div class="qr-corner-mini">
                                                <div class="cm-outer" style="border-radius:<?php echo $cs['outer_br']; ?>;"></div>
                                                <div class="cm-inner" style="border-radius:<?php echo $cs['inner_br']; ?>;"></div>
                                            </div>
                                            <?php echo htmlspecialchars($cs['label']); ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="digital_qr[corner_style]" id="qr-corner-style"
                                        value="<?php echo htmlspecialchars($default_qr_style['corner_style']); ?>">
                                </div>

                                <!-- Çerçeve -->
                                <div class="qr-group">
                                    <div class="qr-group-label">Çerçeve</div>
                                    <div class="qr-style-cards">
                                        <?php
                                        $qr_frame_styles = [
                                            ['value'=>'none',    'label'=>'Yok',     'outer_br'=>'0',   'inner_br'=>'0',   'border'=>'none'],
                                            ['value'=>'classic', 'label'=>'Klasik',  'outer_br'=>'8px', 'inner_br'=>'4px', 'border'=>'2px solid currentColor'],
                                            ['value'=>'soft',    'label'=>'Yumuşak', 'outer_br'=>'18px','inner_br'=>'10px','border'=>'2px solid currentColor'],
                                            ['value'=>'badge',   'label'=>'Rozet',   'outer_br'=>'50%', 'inner_br'=>'50%', 'border'=>'3px solid currentColor'],
                                        ];
                                        foreach ($qr_frame_styles as $frs):
                                            $frs_sel = ($default_qr_style['frame_style'] === $frs['value']);
                                        ?>
                                        <button type="button"
                                            class="qr-style-card <?php echo $frs_sel ? 'selected' : ''; ?>"
                                            data-value="<?php echo htmlspecialchars($frs['value']); ?>"
                                            onclick="selectQrStyle(this,'qr-frame-style')"
                                            title="<?php echo htmlspecialchars($frs['label']); ?>">
                                            <div class="qr-frame-mini"
                                                style="border-radius:<?php echo $frs['outer_br']; ?>;border:<?php echo $frs['border']; ?>;">
                                                <div class="qr-frame-mini-inner"
                                                    style="border-radius:<?php echo $frs['inner_br']; ?>;"></div>
                                            </div>
                                            <?php echo htmlspecialchars($frs['label']); ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="digital_qr[frame_style]" id="qr-frame-style"
                                        value="<?php echo htmlspecialchars($default_qr_style['frame_style']); ?>">
                                </div>

                            </div><!-- /qr-studio-controls -->

                            <!-- ── Önizleme ── -->
                            <div class="qr-studio-preview">
                                <div class="qr-preview-wrap">
                                    <div class="qr-preview-label-badge">
                                        <span class="qr-live-dot"></span>
                                        Canlı Önizleme
                                    </div>
                                    <div id="orderQrPreviewFrame"
                                        class="qr-preview-frame frame-<?php echo htmlspecialchars($default_qr_style['frame_style']); ?>"
                                        style="background:<?php echo htmlspecialchars($default_qr_style['bg_color']); ?>;">
                                        <div id="orderQrCanvas" class="qr-preview-canvas"></div>
                                    </div>
                                    <p class="qr-preview-note-sub">Gerçek QR kodunuz sipariş onayı sonrası oluşturulur.</p>
                                </div>
                            </div>

                        </div><!-- /qr-studio-grid -->
                    </section>

                    <div class="submit-row">
                        <button class="btn-submit" type="submit">
                            <i data-lucide="send"></i> <?php echo $is_preview_mode ? 'Satın Alma Adımına Geç' : 'Siparişi Oluştur'; ?>
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script src="https://unpkg.com/qr-code-styling@1.6.0/lib/qr-code-styling.js"></script>
    <script>
        /* ── Renk Seçici (color_preferences) Premium Fonksiyonları ── */
        function updateBaskiColor(type, value, fieldId) {
            const hex = normalizeHexColor(value);
            if (!hex) return;

            const paperHexEl = document.getElementById(fieldId + '-paper-hex');
            const paperPickerEl = document.getElementById(fieldId + '-paper-picker');
            const textHexEl = document.getElementById(fieldId + '-text-hex');
            const textPickerEl = document.getElementById(fieldId + '-text-picker');
            const patternHexEl = document.getElementById(fieldId + '-pattern-hex');
            const patternPickerEl = document.getElementById(fieldId + '-pattern-picker');
            const hiddenEl = document.getElementById(fieldId);

            if (type === 'paper') {
                if (paperHexEl) paperHexEl.value = hex.toUpperCase();
                if (paperPickerEl) paperPickerEl.value = hex;
                // Preset butonlarını güncelle
                const paperModule = paperHexEl.closest('.color-module');
                paperModule.querySelectorAll('.cp-preset-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.hex.toLowerCase() === hex.toLowerCase());
                });
            } else {
                if (textHexEl) textHexEl.value = hex.toUpperCase();
                if (textPickerEl) textPickerEl.value = hex;
                // Preset butonlarını güncelle
                const textModule = textHexEl.closest('.color-module');
                textModule.querySelectorAll('.cp-preset-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.hex.toLowerCase() === hex.toLowerCase());
                });
            }

            // Gizli field değerini senkronize et
            const pVal = paperHexEl ? paperHexEl.value : '#0F2747';
            const tVal = textHexEl ? textHexEl.value : '#FFFFFF';
            if (hiddenEl) {
                hiddenEl.value = `Paper: ${pVal}; Text: ${tVal}`;
            }
        }

        // Eski fonksiyonlar (uyumluluk için boşaltıldı veya dönüştürüldü)
        function updateBaskiColor(type, value, fieldId) {
            const hex = normalizeHexColor(value);
            if (!hex) return;

            const paperHexEl = document.getElementById(fieldId + '-paper-hex');
            const paperPickerEl = document.getElementById(fieldId + '-paper-picker');
            const textHexEl = document.getElementById(fieldId + '-text-hex');
            const textPickerEl = document.getElementById(fieldId + '-text-picker');
            const patternHexEl = document.getElementById(fieldId + '-pattern-hex');
            const patternPickerEl = document.getElementById(fieldId + '-pattern-picker');
            const hiddenEl = document.getElementById(fieldId);

            if (type === 'paper') {
                if (paperHexEl) paperHexEl.value = hex.toUpperCase();
                if (paperPickerEl) paperPickerEl.value = hex;
            } else if (type === 'text') {
                if (textHexEl) textHexEl.value = hex.toUpperCase();
                if (textPickerEl) textPickerEl.value = hex;
            } else if (type === 'pattern') {
                if (patternHexEl) patternHexEl.value = hex.toUpperCase();
                if (patternPickerEl) patternPickerEl.value = hex;
            }

            const pVal = paperHexEl ? paperHexEl.value : '#0F2747';
            const tVal = textHexEl ? textHexEl.value : '#FFFFFF';
            const patternVal = patternHexEl ? patternHexEl.value : '#A6803F';
            if (hiddenEl) {
                hiddenEl.value = `Paper: ${pVal}; Text: ${tVal}; Pattern: ${patternVal}`;
            }
        }

        function cpApply(baseId, hex, label) {}
        function cpPickCard(btn, baseId) {}
        function cpOnPicker(pickerInput, baseId) {}
        function cpOnHex(textInput, baseId) {}

        function normalizeHexColor(val) {
            if (!val) return null;
            let hex = val.trim();
            if (!hex.startsWith('#')) hex = '#' + hex;
            if (/^#[0-9a-fA-F]{6}$/.test(hex)) return hex;
            return null;
        }

        const ORDER_LOCK_REASON = <?php echo json_encode((string)($package_state['lock_reason'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        if (ORDER_LOCK_REASON === 'order_limit_reached') {
            const lockedCard = document.querySelector('#new-order');
            if (lockedCard) {
                const heading = lockedCard.querySelector('h3');
                const intro = lockedCard.querySelector('p');
                if (heading) heading.textContent = 'Sipariş Hakkı Kullanıldı';
                if (intro) intro.textContent = 'Baskı sipariş hakkınız kullanıldı. Yeni sipariş açamazsınız; dijital profilinizi profil ekranından güncelleyebilirsiniz.';
            }
        }

        const packageInputEls = Array.from(document.querySelectorAll('input[name="order_package"]'));
        const packageOptionEls = Array.from(document.querySelectorAll('[data-order-package-option]'));
        const dynamicFieldEls = Array.from(document.querySelectorAll('[data-df-field]'));

        const qrColorInput = document.getElementById('qr-color-input');
        const qrColorText = document.getElementById('qr-color-text');
        const qrBgInput = document.getElementById('qr-bg-input');
        const qrBgText = document.getElementById('qr-bg-text');
        const qrDotStyle = document.getElementById('qr-dot-style');
        const qrCornerStyle = document.getElementById('qr-corner-style');
        const qrFrameStyle = document.getElementById('qr-frame-style');
        const qrPreviewFrame = document.getElementById('orderQrPreviewFrame');
        const qrCanvas = document.getElementById('orderQrCanvas');

        let qrStyling = null;

        function getSelectedPackage() {
            const checkedInput = packageInputEls.find(i => i.checked);
            return checkedInput ? checkedInput.value.toLowerCase() : 'smart';
        }

        function syncOrderPackageCards() {
            const selected = getSelectedPackage();
            packageOptionEls.forEach(opt => {
                const input = opt.querySelector('input');
                opt.classList.toggle('active', input && input.value === selected);
            });
        }

        function updateDynamicVisibility() {
            const selected = getSelectedPackage();
            dynamicFieldEls.forEach(el => {
                const showOn = el.dataset.showPackages ? el.dataset.showPackages.split(',').map(s => s.trim()) : [];
                const visible = showOn.length === 0 || showOn.includes(selected);
                el.style.display = visible ? 'flex' : 'none';
                const input = el.querySelector('input, textarea, select');
                if (input) {
                    const reqOn = el.dataset.requiredPackages ? el.dataset.requiredPackages.split(',').map(s => s.trim()) : [];
                    input.required = visible && (el.dataset.baseRequired === '1' || reqOn.includes(selected));
                }
            });
        }

        function syncQrColor(textInput) {
            const val = textInput.value;
            if (/^#[0-9A-F]{6}$/i.test(val)) {
                qrColorInput.value = val;
                updateQrPreview();
            }
        }

        function syncQrBg(textInput) {
            const val = textInput.value;
            if (/^#[0-9A-F]{6}$/i.test(val)) {
                qrBgInput.value = val;
                updateQrPreview();
            }
        }

        function selectQrStyle(btn, inputId) {
            const cards = btn.closest('.qr-style-cards');
            if (cards) {
                cards.querySelectorAll('.qr-style-card').forEach(b => b.classList.remove('selected'));
            }
            btn.classList.add('selected');
            const hidden = document.getElementById(inputId);
            if (hidden) hidden.value = btn.dataset.value;
            updateQrPreview();
        }

        function updateQrPreview() {
            if (!qrCanvas) return;
            qrColorText.value = qrColorInput.value.toUpperCase();
            qrBgText.value = qrBgInput.value.toUpperCase();

            const frameVal = qrFrameStyle ? qrFrameStyle.value : 'classic';
            const cornerVal = qrCornerStyle ? qrCornerStyle.value : 'square';
            const dotVal = qrDotStyle ? qrDotStyle.value : 'square';

            qrPreviewFrame.className = `qr-preview-frame frame-${frameVal}`;
            qrPreviewFrame.style.background = qrBgInput.value;

            const cornersSquareType = cornerVal === 'extra-rounded' ? 'extra-rounded' : 'square';
            const cornersDotType = (cornerVal === 'dot' || cornerVal === 'extra-rounded') ? 'dot' : 'square';

            const options = {
                width: 186,
                height: 186,
                data: "https://zerosoft.com.tr",
                dotsOptions: { color: qrColorInput.value, type: dotVal },
                backgroundOptions: { color: qrBgInput.value },
                cornersSquareOptions: { type: cornersSquareType, color: qrColorInput.value },
                cornersDotOptions: { type: cornersDotType, color: qrColorInput.value },
                qrOptions: { errorCorrectionLevel: 'M' }
            };

            if (!qrStyling) {
                qrStyling = new QRCodeStyling(options);
                qrStyling.append(qrCanvas);
            } else {
                qrStyling.update(options);
            }
        }

        packageInputEls.forEach(input => {
            input.addEventListener('change', () => {
                syncOrderPackageCards();
                updateDynamicVisibility();
            });
        });

        qrColorInput.addEventListener('input', updateQrPreview);
        qrBgInput.addEventListener('input', updateQrPreview);

        syncOrderPackageCards();
        updateDynamicVisibility();
        updateQrPreview();
        lucide.createIcons();
    </script>
</body>
</html>
