<?php
require_once __DIR__ . '/../core/security.php';
ensure_session_started();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/customer_access.php';
require_once __DIR__ . '/../core/dynamic_form.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../customer/new-order.php?order_error=csrf');

$user_id = (int) $_SESSION['user_id'];
df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

function dashboard_redirect(string $query = ''): void
{
    $suffix = '#new-order';
    $target = '../customer/new-order.php';
    if ($query !== '') {
        $target .= '?' . ltrim($query, '?');
    }
    header('Location: ' . $target . $suffix);
    exit();
}

function purchase_review_redirect(string $query = ''): void
{
    $target = '../customer/purchase-review.php';
    if ($query !== '') {
        $target .= '?' . ltrim($query, '?');
    }
    header('Location: ' . $target);
    exit();
}

function table_exists(PDO $pdo, string $table): bool
{
    return df_table_exists($pdo, $table);
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    return df_table_has_column($pdo, $table, $column);
}

function resolve_customer_locked_package(PDO $pdo, int $user_id): ?string
{
    $package = qrk_get_customer_package_slug($pdo, $user_id);
    return $package !== '' ? $package : null;
}

function resolve_field_answer_value(array $field, string $raw_value): string
{
    $value = trim($raw_value);
    if ($value === '') {
        return '';
    }

    if (strtolower((string) ($field['field_type'] ?? '')) !== 'select') {
        return $value;
    }

    foreach (($field['options'] ?? []) as $option) {
        if (trim((string) ($option['option_value'] ?? '')) !== $value) {
            continue;
        }

        $label = trim((string) ($option['option_label'] ?? ''));
        return $label !== '' ? $label : $value;
    }

    return $value;
}

function build_print_brief(array $resolved_answers): string
{
    $brief_order = [
        'full_name' => 'Ad Soyad',
        'company_name' => 'Şirket / Marka Adı',
        'job_title' => 'Mesleki Unvan',
        'print_quantity' => 'Baskı Adedi / Paket Talebi',
        'card_size' => 'Kart Ölçüsü',
        'card_orientation' => 'Yerleşim Yönü',
        'print_sides' => 'Baskı Yüzü',
        'material_type' => 'Kağıt / Malzeme',
        'lamination_finish' => 'Yüzey / Selefon',
        'design_style' => 'Tasarım Stili',
        'color_preferences' => 'Renk / Kurumsal Renk Bilgisi',
        'card_content_brief' => 'Kartta Yer Alacak Bilgiler',
        'back_side_brief' => 'Arka Yüz İçeriği',
        'special_finish' => 'Özel Uygulama',
        'reference_examples' => 'Referans / Beğendiğiniz Örnekler',
        'print_requirements' => 'Ek Baskı Notları',
    ];

    $lines = [];
    foreach ($brief_order as $field_key => $fallback_label) {
        $value = trim((string) ($resolved_answers[$field_key]['value_text'] ?? ''));
        if ($value === '') {
            continue;
        }

        $label = trim((string) ($resolved_answers[$field_key]['field_label'] ?? $fallback_label));
        if ($label === '') {
            $label = $fallback_label;
        }

        $lines[] = '- ' . $label . ': ' . $value;
    }

    return implode("\n", $lines);
}

function normalize_order_notes(string $design_notes, array $resolved_answers, bool $includes_print): string
{
    $blocks = [];
    $clean_design_notes = trim($design_notes);

    if ($clean_design_notes !== '') {
        $blocks[] = "Genel Tasarım Notları:\n" . $clean_design_notes;
    }

    if ($includes_print) {
        $print_brief = build_print_brief($resolved_answers);
        if ($print_brief !== '') {
            $blocks[] = "Baskı Brifi:\n" . $print_brief;
        }
    }

    return implode("\n\n", $blocks);
}

function normalize_hex_color(string $raw): ?string
{
    $value = strtoupper(trim($raw));
    if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
        return null;
    }
    return $value;
}

function normalize_enum(string $raw, array $allowed, string $default): string
{
    $value = strtolower(trim($raw));
    return in_array($value, $allowed, true) ? $value : $default;
}

function normalize_initial_text(string $raw, string $fallback = 'A'): string
{
    $value = trim($raw);
    if ($value === '') {
        $value = $fallback;
    }
    $value = preg_replace('/\s+/u', '', $value);
    $value = preg_replace('/\p{C}+/u', '', (string)$value);
    if (!is_string($value) || $value === '') {
        $value = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 2, 'UTF-8');
    }

    return substr($value, 0, 2);
}

function derive_initial_from_name(string $name, string $fallback = 'A'): string
{
    $clean_name = trim($name);
    if ($clean_name === '') {
        return normalize_initial_text($fallback, 'A');
    }

    $parts = preg_split('/\s+/u', $clean_name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '') {
            continue;
        }
        if (function_exists('mb_substr')) {
            $letters .= mb_substr($part, 0, 1, 'UTF-8');
        } else {
            $letters .= substr($part, 0, 1);
        }
        if ((function_exists('mb_strlen') ? mb_strlen($letters, 'UTF-8') : strlen($letters)) >= 2) {
            break;
        }
    }

    if ($letters === '') {
        $letters = $clean_name;
    }

    return normalize_initial_text($letters, $fallback);
}

function normalize_platform_key(string $raw_platform): ?string
{
    $platform = strtolower(trim($raw_platform));
    if ($platform === '') {
        return null;
    }

    $aliases = ['twitter' => 'x', 'x-twitter' => 'x'];
    if (isset($aliases[$platform])) {
        $platform = $aliases[$platform];
    }

    $platform = preg_replace('/[^a-z0-9_-]+/', '_', $platform);
    if (!is_string($platform)) {
        return null;
    }
    $platform = trim($platform, '_-');
    if ($platform === '') {
        return null;
    }

    return substr($platform, 0, 50);
}

function normalize_social_url(string $platform, string $raw_url): ?string
{
    $url = trim($raw_url);
    if ($url === '') {
        return null;
    }

    if ($platform === 'mail') {
        $email_candidate = str_starts_with(strtolower($url), 'mailto:') ? substr($url, 7) : $url;
        if (filter_var($email_candidate, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:' . $email_candidate;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return null;
    }

    if ($platform === 'whatsapp') {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        $digits = preg_replace('/\D+/', '', $url);
        if (!is_string($digits) || $digits === '') {
            return null;
        }
        return 'https://wa.me/' . $digits;
    }

    if ($platform === 'phone') {
        $digits = preg_replace('/\D+/', '', $url);
        if (!is_string($digits) || $digits === '') {
            return null;
        }
        return 'tel:+' . ltrim($digits, '+');
    }

    if ($platform === 'maps') {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        return 'https://maps.google.com/?q=' . rawurlencode($url);
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

function normalize_existing_social_logo_path(string $raw): ?string
{
    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    if (!preg_match('#^assets/uploads/social_logos/[A-Za-z0-9._-]+$#', $value)) {
        return null;
    }

    return $value;
}

function resolve_package_price(PDO $pdo, string $package): float
{
    $fallback_map = [
        'classic' => 299.00,
        'panel' => 700.00,
        'smart' => 1200.00,
    ];

    if (!table_exists($pdo, 'packages') || !table_has_column($pdo, 'packages', 'slug') || !table_has_column($pdo, 'packages', 'price')) {
        return $fallback_map[$package] ?? 0.0;
    }

    $stmt = $pdo->prepare('SELECT price FROM packages WHERE slug = ? LIMIT 1');
    $stmt->execute([$package]);
    $price = $stmt->fetchColumn();

    if ($price === false) {
        return $fallback_map[$package] ?? 0.0;
    }

    return (float) $price;
}

function upload_logo_file(array $file, int $user_id): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('logo_upload_error');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('logo_too_large');
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);

    if (!isset($allowed_mimes[$mime])) {
        throw new RuntimeException('logo_invalid_type');
    }

    $uploads_dir = __DIR__ . '/../assets/uploads/logos/';
    if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
        throw new RuntimeException('logo_dir_failed');
    }

    $file_name = 'logo_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $logo_path = 'assets/uploads/logos/' . $file_name;

    if (!move_uploaded_file($tmp_name, __DIR__ . '/../' . $logo_path)) {
        throw new RuntimeException('logo_move_failed');
    }

    return $logo_path;
}

function upload_profile_asset(array $file, int $user_id, string $prefix, string $error_prefix): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error_prefix . '_upload_error');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException($error_prefix . '_too_large');
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $tmp_name = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);
    if (!isset($allowed_mimes[$mime])) {
        throw new RuntimeException($error_prefix . '_invalid_type');
    }

    $upload_dir = __DIR__ . '/../assets/uploads/profiles/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new RuntimeException($error_prefix . '_dir_failed');
    }

    $file_name = $prefix . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $relative_path = 'assets/uploads/profiles/' . $file_name;

    if (!move_uploaded_file($tmp_name, __DIR__ . '/../' . $relative_path)) {
        throw new RuntimeException($error_prefix . '_move_failed');
    }

    return $relative_path;
}

function upload_social_logo_file(array $file_array, int $index, int $user_id): ?string
{
    $error = (int)($file_array['error'][$index] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('social_logo_upload_error');
    }

    $size = (int)($file_array['size'][$index] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('social_logo_too_large');
    }

    $tmp_name = (string)($file_array['tmp_name'][$index] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        throw new RuntimeException('social_logo_upload_error');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp_name);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed_mimes[$mime])) {
        throw new RuntimeException('social_logo_invalid_type');
    }

    $upload_dir = __DIR__ . '/../assets/uploads/social_logos/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        throw new RuntimeException('social_logo_dir_failed');
    }

    $file_name = 'social_logo_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $relative_path = 'assets/uploads/social_logos/' . $file_name;
    if (!move_uploaded_file($tmp_name, __DIR__ . '/../' . $relative_path)) {
        throw new RuntimeException('social_logo_move_failed');
    }

    return $relative_path;
}

function generate_unique_profile_slug(PDO $pdo, string $seed): string
{
    $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $seed)));
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'profil';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM profiles WHERE slug = ?');
    for ($i = 0; $i < 20; $i++) {
        $candidate = $base . '-' . random_int(100, 9999);
        $stmt->execute([$candidate]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
    }

    return $base . '-' . time();
}

function project_base_url_from_request(): string
{
    $env_app_url = trim((string) getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/processes/customer_order_create.php');
    $project_path = preg_replace('#/processes/[^/]+$#', '', $script_name);

    return $scheme . '://' . $host . rtrim((string) $project_path, '/');
}

function build_dynamic_qr_url(string $target_url): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&ecc=M&data=' . rawurlencode($target_url);
}

function resolve_package_id(PDO $pdo, string $package): ?int
{
    if (!table_exists($pdo, 'packages') || !table_has_column($pdo, 'packages', 'slug') || !table_has_column($pdo, 'packages', 'id')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM packages WHERE slug = ? LIMIT 1');
    $stmt->execute([$package]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
}

$order_package = strtolower(trim((string) ($_POST['order_package'] ?? 'smart')));
$allowed_packages = ['classic', 'smart', 'panel'];
if (!in_array($order_package, $allowed_packages, true)) {
    $order_package = 'smart';
}
$package_state = qrk_get_customer_package_state($pdo, $user_id, $order_package);
$is_preview_mode = $package_state['package_slug'] === ''
    && (string)($package_state['pending_package_slug'] ?? '') !== ''
    && (string)($package_state['pending_package_mode'] ?? '') === 'preview';
if ($package_state['package_slug'] === '' && !$package_state['can_create_order'] && !$is_preview_mode) {
    dashboard_redirect('order_error=no_credit');
}
if (($package_state['lock_reason'] ?? '') === 'order_limit_reached') {
    dashboard_redirect('order_error=order_limit_reached');
}

$customer_locked_package = $package_state['package_slug'] !== ''
    ? $package_state['package_slug']
    : ($is_preview_mode
        ? (string)($package_state['pending_package_slug'] ?? '')
        : resolve_customer_locked_package($pdo, $user_id));
if ($customer_locked_package !== null) {
    $order_package = $customer_locked_package;
}

$posted_dynamic_fields = $_POST['dynamic_fields'] ?? [];
if (!is_array($posted_dynamic_fields)) {
    $posted_dynamic_fields = [];
}

$posted_digital_qr = $_POST['digital_qr'] ?? [];
if (!is_array($posted_digital_qr)) {
    $posted_digital_qr = [];
}

$digital_qr_color = normalize_hex_color((string)($posted_digital_qr['color'] ?? ''));
$digital_qr_bg_color = normalize_hex_color((string)($posted_digital_qr['bg_color'] ?? ''));
$digital_qr_dot_style = normalize_enum((string)($posted_digital_qr['dot_style'] ?? ''), ['square', 'dots', 'rounded', 'classy', 'classy-rounded', 'extra-rounded'], 'square');
$digital_qr_corner_style = normalize_enum((string)($posted_digital_qr['corner_style'] ?? ''), ['square', 'dot', 'extra-rounded'], 'square');
$digital_qr_frame_style = normalize_enum((string)($posted_digital_qr['frame_style'] ?? ''), ['classic', 'soft', 'badge', 'none'], 'classic');

$posted_digital_profile = $_POST['digital_profile'] ?? [];
if (!is_array($posted_digital_profile)) {
    $posted_digital_profile = [];
}
$digital_display_name = trim((string)($posted_digital_profile['display_name'] ?? ''));
$digital_bio = trim((string)($posted_digital_profile['bio'] ?? ''));
$digital_cover_color = '#0A2F2F';
$digital_avatar_color = '#0A2F2F';
$digital_avatar_initial_raw = '';

$posted_digital_links = $_POST['digital_links'] ?? [];
if (!is_array($posted_digital_links)) {
    $posted_digital_links = [];
}
$digital_link_platforms = is_array($posted_digital_links['platforms'] ?? null) ? array_values($posted_digital_links['platforms']) : [];
$digital_link_urls = is_array($posted_digital_links['urls'] ?? null) ? array_values($posted_digital_links['urls']) : [];
$digital_link_customs = is_array($posted_digital_links['customs'] ?? null) ? array_values($posted_digital_links['customs']) : [];
$digital_link_existing_logos = is_array($posted_digital_links['existing_logos'] ?? null) ? array_values($posted_digital_links['existing_logos']) : [];
$digital_link_logo_files = $_FILES['digital_link_logos'] ?? [];

$form_fields = df_get_form_fields($pdo, true);
$resolved_answers = [];
$has_required_error = false;

foreach ($form_fields as $field) {
    $field_key = (string) ($field['field_key'] ?? '');
    if ($field_key === '') {
        continue;
    }

    $is_active = (int) ($field['is_active'] ?? 0) === 1;
    $is_visible_for_package = $is_active && df_field_is_visible_for_package($field, $order_package);
    $is_required = $is_visible_for_package && df_field_is_required_for_package($field, $order_package);

    if ($is_visible_for_package) {
        $raw_value = trim((string) ($posted_dynamic_fields[$field_key] ?? ''));
        $value = resolve_field_answer_value($field, $raw_value);
        $value_source = 'customer';
        if ($is_required && $raw_value === '') {
            $has_required_error = true;
        }
    } else {
        $value = resolve_field_answer_value($field, (string) ($field['default_value'] ?? ''));
        $value_source = 'default';
    }

    $resolved_answers[$field_key] = [
        'field_key' => $field_key,
        'field_label' => (string) ($field['field_label'] ?? $field_key),
        'value_text' => $value,
        'value_source' => $value_source,
    ];
}

$_SESSION['customer_order_old_input'] = [
    'order_package' => $order_package,
    'dynamic_fields' => $posted_dynamic_fields,
    'digital_qr' => [
        'color' => '#0A2F2F',
        'bg_color' => '#FFFFFF',
        'dot_style' => 'square',
        'corner_style' => 'square',
        'frame_style' => 'soft',
    ],
    'digital_profile' => [
        'display_name' => $digital_display_name,
        'bio' => $digital_bio,
    ],
    'digital_links' => [
        'platforms' => $digital_link_platforms,
        'urls' => $digital_link_urls,
        'customs' => $digital_link_customs,
        'existing_logos' => $digital_link_existing_logos,
    ],
];

if ($is_preview_mode) {
    purchase_review_redirect('status=order_gate');
}

if ($has_required_error) {
    dashboard_redirect('order_error=required_fields');
}

$company_name = trim((string) ($resolved_answers['company_name']['value_text'] ?? ''));
$job_title = trim((string) ($resolved_answers['job_title']['value_text'] ?? ''));
$design_notes = trim((string) ($resolved_answers['design_notes']['value_text'] ?? ''));
$includes_print = in_array($order_package, ['classic', 'smart'], true);

try {
    $pdo->beginTransaction();

    $logo_path = upload_logo_file($_FILES['logo'] ?? [], $user_id);
    $revision_count = $order_package === 'panel' ? 0 : 2;
    $status = $order_package === 'panel' ? 'completed' : 'pending';
    $final_notes = normalize_order_notes($design_notes, $resolved_answers, $includes_print);

    $order_columns = ['user_id'];
    $order_values = [$user_id];

    if (table_has_column($pdo, 'orders', 'package')) {
        $order_columns[] = 'package';
        $order_values[] = $order_package;
    }
    if (table_has_column($pdo, 'orders', 'package_id')) {
        $order_columns[] = 'package_id';
        $order_values[] = resolve_package_id($pdo, $order_package);
    }
    if (table_has_column($pdo, 'orders', 'company_name')) {
        $order_columns[] = 'company_name';
        $order_values[] = $company_name;
    }
    if (table_has_column($pdo, 'orders', 'job_title')) {
        $order_columns[] = 'job_title';
        $order_values[] = $job_title;
    }
    if (table_has_column($pdo, 'orders', 'logo_path')) {
        $order_columns[] = 'logo_path';
        $order_values[] = $logo_path;
    }
    if (table_has_column($pdo, 'orders', 'design_notes')) {
        $order_columns[] = 'design_notes';
        $order_values[] = $final_notes;
    }
    if (table_has_column($pdo, 'orders', 'revision_count')) {
        $order_columns[] = 'revision_count';
        $order_values[] = $revision_count;
    }
    if (table_has_column($pdo, 'orders', 'current_revision_count')) {
        $order_columns[] = 'current_revision_count';
        $order_values[] = 0;
    }
    if (table_has_column($pdo, 'orders', 'total_allowed_revisions')) {
        $order_columns[] = 'total_allowed_revisions';
        $order_values[] = $revision_count;
    }
    if (table_has_column($pdo, 'orders', 'status')) {
        $order_columns[] = 'status';
        $order_values[] = $status;
    }

    $order_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $order_columns));
    $order_placeholders = implode(', ', array_fill(0, count($order_values), '?'));
    $stmt_order = $pdo->prepare("INSERT INTO orders ({$order_columns_sql}) VALUES ({$order_placeholders})");
    $stmt_order->execute($order_values);
    $order_id = (int) $pdo->lastInsertId();

    df_upsert_order_answers($pdo, $order_id, array_values($resolved_answers));

    if (table_exists($pdo, 'payments')) {
        $package_price = resolve_package_price($pdo, $order_package);
        $payment_columns = [];
        $payment_values = [];

        if (table_has_column($pdo, 'payments', 'user_id')) {
            $payment_columns[] = 'user_id';
            $payment_values[] = $user_id;
        }
        if (table_has_column($pdo, 'payments', 'order_id')) {
            $payment_columns[] = 'order_id';
            $payment_values[] = $order_id;
        }
        if (table_has_column($pdo, 'payments', 'transaction_id')) {
            $payment_columns[] = 'transaction_id';
            $payment_values[] = 'ORD-' . date('YmdHis') . '-' . $order_id . '-' . random_int(1000, 9999);
        }
        if (table_has_column($pdo, 'payments', 'amount')) {
            $payment_columns[] = 'amount';
            $payment_values[] = $package_price;
        }
        if (table_has_column($pdo, 'payments', 'currency')) {
            $payment_columns[] = 'currency';
            $payment_values[] = 'TRY';
        }
        if (table_has_column($pdo, 'payments', 'type')) {
            $payment_columns[] = 'type';
            $payment_values[] = 'order';
        }
        if (table_has_column($pdo, 'payments', 'payment_type')) {
            $payment_columns[] = 'payment_type';
            $payment_values[] = 'order';
        }
        if (table_has_column($pdo, 'payments', 'status')) {
            $payment_columns[] = 'status';
            $payment_values[] = 'success';
        }
        if (table_has_column($pdo, 'payments', 'payment_details')) {
            $payment_columns[] = 'payment_details';
            $payment_values[] = json_encode(
                [
                    'source' => 'customer_panel',
                    'package' => $order_package,
                    'includes_print' => $includes_print,
                    'dynamic_form' => true,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        if (!empty($payment_columns)) {
            $payment_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $payment_columns));
            $payment_placeholders = implode(', ', array_fill(0, count($payment_values), '?'));
            $stmt_payment = $pdo->prepare("INSERT INTO payments ({$payment_columns_sql}) VALUES ({$payment_placeholders})");
            $stmt_payment->execute($payment_values);
        }
    }

    $pdo->commit();

    if (table_exists($pdo, 'profiles')) {
        try {
            // QR Stilini Güncelle (Ortak Alan)
            $qr_style_payload = json_encode([
                'color' => $digital_qr_color ?: '#0A2F2F',
                'bg_color' => $digital_qr_bg_color ?: '#FFFFFF',
                'dot_style' => $digital_qr_dot_style,
                'corner_style' => $digital_qr_corner_style,
                'frame_style' => $digital_qr_frame_style
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt_profile = $pdo->prepare('SELECT id, slug FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1');
            $stmt_profile->execute([$user_id]);
            $profile_row = $stmt_profile->fetch();

            if ($profile_row) {
                $profile_id = (int)$profile_row['id'];
                $profile_slug = (string)$profile_row['slug'];
                
                $profile_updates = ["qr_style = ?"];
                $profile_params = [$qr_style_payload];

                if (table_has_column($pdo, 'profiles', 'qr_path') && $profile_slug !== '') {
                    $public_url = project_base_url_from_request() . '/kartvizit.php?slug=' . rawurlencode($profile_slug);
                    $profile_updates[] = "qr_path = ?";
                    $profile_params[] = build_dynamic_qr_url($public_url);
                }

                $profile_params[] = $profile_id;
                $stmt_upd = $pdo->prepare("UPDATE profiles SET " . implode(', ', $profile_updates) . " WHERE id = ?");
                $stmt_upd->execute($profile_params);
            } else {
                $stmt_user = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $stmt_user->execute([$user_id]);
                $user_name = $stmt_user->fetchColumn() ?: 'Müşteri';
                
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $user_name), '-')) . '-' . rand(100, 999);
                $stmt_ins = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name, qr_style) VALUES (?, ?, ?, ?)");
                $stmt_ins->execute([$user_id, $slug, $user_name, $qr_style_payload]);
            }
        } catch (Throwable $profile_error) {
            error_log("Order Profile Sync Error: " . $profile_error->getMessage());
        }
    }
    unset($_SESSION['customer_order_old_input']);
    unset($_SESSION['default_order_package']);
    dashboard_redirect('order_notice=created');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error_key = (string) $e->getMessage();
    $known_error_keys = [
        'logo_upload_error',
        'logo_too_large',
        'logo_invalid_type',
        'logo_dir_failed',
        'logo_move_failed',
        'profile_photo_upload_error',
        'profile_photo_too_large',
        'profile_photo_invalid_type',
        'profile_photo_dir_failed',
        'profile_photo_move_failed',
        'cover_photo_upload_error',
        'cover_photo_too_large',
        'cover_photo_invalid_type',
        'cover_photo_dir_failed',
        'cover_photo_move_failed',
        'social_logo_upload_error',
        'social_logo_too_large',
        'social_logo_invalid_type',
        'social_logo_dir_failed',
        'social_logo_move_failed',
    ];
    if (in_array($error_key, $known_error_keys, true)) {
        dashboard_redirect('order_error=' . urlencode($error_key));
    }

    error_log('customer_order_create_error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    dashboard_redirect('order_error=create_failed');
}

