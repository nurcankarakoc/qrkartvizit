<?php
session_start();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';

function redirect_with_fallback(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit();
    }

    $safe_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $safe_url . '">';
    echo '<script>window.location.href=' . json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '</head><body></body></html>';
    exit();
}

function write_register_error_log(string $message): void
{
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($log_dir . '/register_process.log', $line, FILE_APPEND);
}

function collect_register_old_input(array $source): array
{
    $keys = [
        'package',
        'name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'design_notes',
        'panel_display_name',
        'theme_color',
        'theme_color_custom',
        'panel_bio',
        'panel_website',
        'panel_address',
        'digital_profile_enabled',
        'current_step',
    ];

    $old = [];
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_string($source[$key])) {
            $old[$key] = $source[$key];
        }
    }

    if (isset($source['social_platforms']) && is_array($source['social_platforms'])) {
        $old['social_platforms'] = array_map(static fn($v) => is_string($v) ? $v : '', $source['social_platforms']);
    }
    if (isset($source['social_urls']) && is_array($source['social_urls'])) {
        $old['social_urls'] = array_map(static fn($v) => is_string($v) ? $v : '', $source['social_urls']);
    }
    if (isset($source['social_platform_customs']) && is_array($source['social_platform_customs'])) {
        $old['social_platform_customs'] = array_map(static fn($v) => is_string($v) ? $v : '', $source['social_platform_customs']);
    }

    return $old;
}

function redirect_register_error(string $error_code, int $step = 1, bool $preserve_input = true): void
{
    if ($preserve_input) {
        $_SESSION['register_old_input'] = collect_register_old_input($_POST);
    } else {
        unset($_SESSION['register_old_input']);
    }

    $safe_step = max(1, min(3, $step));
    $_SESSION['register_error_step'] = $safe_step;
    redirect_with_fallback('../auth/register.php?error=' . urlencode($error_code));
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

    $tmp_name = (string)($file['tmp_name'] ?? '');
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

function upload_profile_photo_file(array $file, int $user_id): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('profile_photo_upload_error');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('profile_photo_too_large');
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
        throw new RuntimeException('profile_photo_invalid_type');
    }

    $uploads_dir = __DIR__ . '/../assets/uploads/profiles/';
    if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
        throw new RuntimeException('profile_photo_dir_failed');
    }

    $file_name = 'profile_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $photo_path = 'assets/uploads/profiles/' . $file_name;

    if (!move_uploaded_file($tmp_name, __DIR__ . '/../' . $photo_path)) {
        throw new RuntimeException('profile_photo_move_failed');
    }

    return $photo_path;
}

function table_exists(PDO $pdo, string $table): bool
{
    $table_escaped = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
    return (bool)$stmt->fetchColumn();
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!table_exists($pdo, $table)) {
        return false;
    }

    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

function has_digital_profile_package(string $package): bool
{
    return in_array($package, ['panel', 'smart'], true);
}

function normalize_theme_color(string $raw_color): string
{
    $color = trim($raw_color);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return strtoupper($color);
    }
    return '#0A2F2F';
}

function project_base_url_from_request(): string
{
    $env_app_url = trim((string)getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/processes/register_process.php');
    $project_path = preg_replace('#/processes/[^/]+$#', '', $script_name);

    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

function build_dynamic_qr_url(string $target_url): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&ecc=M&data=' . rawurlencode($target_url);
}

function resolve_package_price(PDO $pdo, string $package): float
{
    $fallback_map = [
        'classic' => 299.00,
        'panel' => 700.00,
        'smart' => 1200.00,
    ];
    $legacy_map = [
        'panel' => 199.00,
        'smart' => 499.00,
    ];

    if (!table_exists($pdo, 'packages') || !table_has_column($pdo, 'packages', 'slug') || !table_has_column($pdo, 'packages', 'price')) {
        return $fallback_map[$package] ?? 0.0;
    }

    $stmt = $pdo->prepare("SELECT price FROM packages WHERE slug = ? LIMIT 1");
    $stmt->execute([$package]);
    $price = $stmt->fetchColumn();

    if ($price === false) {
        return $fallback_map[$package] ?? 0.0;
    }

    $resolved_price = (float)$price;
    if (isset($legacy_map[$package]) && abs($resolved_price - $legacy_map[$package]) < 0.0001) {
        return $fallback_map[$package] ?? $resolved_price;
    }

    return $resolved_price;
}

function normalize_social_url(string $platform, string $raw_url): ?string
{
    $url = trim($raw_url);
    if ($url === '') {
        return null;
    }

    if ($platform === 'mail') {
        $email_candidate = str_starts_with(strtolower($url), 'mailto:')
            ? substr($url, 7)
            : $url;

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

        $query = trim($url);
        if ($query === '') {
            return null;
        }

        return 'https://maps.google.com/?q=' . rawurlencode($query);
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

function normalize_platform_key(string $raw_platform): ?string
{
    $platform = strtolower(trim($raw_platform));
    if ($platform === '') {
        return null;
    }

    $aliases = [
        'twitter' => 'x',
        'x-twitter' => 'x',
    ];
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

function collect_social_links_from_request(): array
{
    $platforms = $_POST['social_platforms'] ?? [];
    $urls = $_POST['social_urls'] ?? [];
    $platform_customs = $_POST['social_platform_customs'] ?? [];

    if (!is_array($platforms) || !is_array($urls)) {
        return [];
    }

    if (!is_array($platform_customs)) {
        $platform_customs = [];
    }

    $result = [];

    foreach ($platforms as $index => $platform_raw) {
        $selected_platform = strtolower(trim((string)$platform_raw));
        $platform_candidate = $selected_platform === '__custom__'
            ? (string)($platform_customs[$index] ?? '')
            : $selected_platform;

        $platform = normalize_platform_key($platform_candidate);
        if ($platform === null) {
            continue;
        }

        $url = normalize_social_url($platform, (string)($urls[$index] ?? ''));
        if ($url === null) {
            continue;
        }

        $result[] = ['platform' => $platform, 'url' => $url];
        if (count($result) >= 25) {
            break;
        }
    }

    return $result;
}

function generate_unique_profile_slug(PDO $pdo, string $seed): string
{
    $base = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $seed)));
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'profil';
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM profiles WHERE slug = ?');
    for ($i = 0; $i < 20; $i++) {
        $candidate = $base . '-' . random_int(100, 9999);
        $stmt->execute([$candidate]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
    }

    return $base . '-' . time();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_register_error('required', 1, false);
}

verify_csrf_or_redirect('../auth/register.php?error=csrf');

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$phone = trim((string)($_POST['phone'] ?? ''));
$package = strtolower(trim((string)($_POST['package'] ?? 'smart')));
$kvkk_approved = isset($_POST['kvkk_approved']) ? 1 : 0;
$company_name = trim((string)($_POST['company_name'] ?? ''));
$job_title = trim((string)($_POST['job_title'] ?? ''));
$design_notes = trim((string)($_POST['design_notes'] ?? ''));

$panel_display_name = trim((string)($_POST['panel_display_name'] ?? ''));
$panel_bio = trim((string)($_POST['panel_bio'] ?? ''));
$panel_website_raw = trim((string)($_POST['panel_website'] ?? ''));
$panel_address = trim((string)($_POST['panel_address'] ?? ''));
$theme_color_input = trim((string)($_POST['theme_color_custom'] ?? ''));
if ($theme_color_input === '') {
    $theme_color_input = (string)($_POST['theme_color'] ?? '#0A2F2F');
}
$theme_color = normalize_theme_color($theme_color_input);
$social_links = collect_social_links_from_request();
$current_step = (int)($_POST['current_step'] ?? 1);

if ($name === '' || $email === '' || $password === '') {
    redirect_register_error('required', max(1, $current_step));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_register_error('invalid_email', max(2, $current_step));
}

if (!$kvkk_approved) {
    redirect_register_error('kvkk', 3);
}

$allowed_packages = ['classic', 'smart', 'panel'];
if (!in_array($package, $allowed_packages, true)) {
    $package = 'smart';
}

$is_digital_profile_active = has_digital_profile_package($package);
if (!$is_digital_profile_active) {
    $panel_display_name = '';
    $panel_bio = '';
    $panel_website_raw = '';
    $panel_address = '';
    $social_links = [];
}

if ($panel_display_name === '') {
    $panel_display_name = $name;
}

$panel_website = normalize_social_url('website', $panel_website_raw) ?? '';

try {
    $pdo->beginTransaction();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_columns = ['name', 'email', 'password', 'phone', 'role'];
    $user_values = [$name, $email, $hashed_password, $phone, 'customer'];
    if (table_has_column($pdo, 'users', 'kvkk_approved')) {
        $user_columns[] = 'kvkk_approved';
        $user_values[] = $kvkk_approved;
    }

    $user_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $user_columns));
    $user_placeholders = implode(', ', array_fill(0, count($user_values), '?'));
    $stmt = $pdo->prepare("INSERT INTO users ({$user_columns_sql}) VALUES ({$user_placeholders})");
    $stmt->execute($user_values);
    $user_id = (int)$pdo->lastInsertId();

    $logo_path = upload_logo_file($_FILES['logo'] ?? [], $user_id);
    $profile_photo_path = $is_digital_profile_active
        ? upload_profile_photo_file($_FILES['panel_photo'] ?? [], $user_id)
        : null;

    $revision_count = ($package === 'panel') ? 0 : 2;
    $order_columns = ['user_id'];
    $order_values = [$user_id];

    if (table_has_column($pdo, 'orders', 'package')) {
        $order_columns[] = 'package';
        $order_values[] = $package;
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
        $order_values[] = $design_notes;
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
        $order_values[] = $package === 'panel' ? 'completed' : 'pending';
    }

    $order_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $order_columns));
    $order_placeholders = implode(', ', array_fill(0, count($order_values), '?'));
    $stmt = $pdo->prepare("INSERT INTO orders ({$order_columns_sql}) VALUES ({$order_placeholders})");
    $stmt->execute($order_values);
    $order_id = (int)$pdo->lastInsertId();

    if (table_exists($pdo, 'payments')) {
        $package_price = resolve_package_price($pdo, $package);
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
                    'source' => 'register',
                    'package' => $package,
                    'digital_profile_active' => $is_digital_profile_active,
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

    $slug = generate_unique_profile_slug($pdo, $panel_display_name !== '' ? $panel_display_name : $name);
    $public_profile_url = project_base_url_from_request() . '/kartvizit.php?slug=' . rawurlencode($slug);
    $dynamic_qr_url = build_dynamic_qr_url($public_profile_url);

    $profile_columns = ['user_id', 'slug'];
    $profile_values = [$user_id, $slug];

    $profile_optional_map = [
        'full_name' => $panel_display_name,
        'title' => $job_title,
        'company' => $company_name,
        'phone_work' => $phone,
        'email_work' => $email,
    ];
    foreach ($profile_optional_map as $column => $value) {
        if (table_has_column($pdo, 'profiles', $column)) {
            $profile_columns[] = $column;
            $profile_values[] = $value;
        }
    }

    if (table_has_column($pdo, 'profiles', 'order_id')) {
        $profile_columns[] = 'order_id';
        $profile_values[] = $order_id;
    }
    if (table_has_column($pdo, 'profiles', 'bio')) {
        $profile_columns[] = 'bio';
        $profile_values[] = $panel_bio;
    }
    if (table_has_column($pdo, 'profiles', 'website')) {
        $profile_columns[] = 'website';
        $profile_values[] = $panel_website;
    }
    if (table_has_column($pdo, 'profiles', 'address')) {
        $profile_columns[] = 'address';
        $profile_values[] = $panel_address;
    }
    if (table_has_column($pdo, 'profiles', 'theme_color')) {
        $profile_columns[] = 'theme_color';
        $profile_values[] = $theme_color;
    }
    if (table_has_column($pdo, 'profiles', 'brand_color')) {
        $profile_columns[] = 'brand_color';
        $profile_values[] = $theme_color;
    }
    if ($profile_photo_path !== null && table_has_column($pdo, 'profiles', 'photo_path')) {
        $profile_columns[] = 'photo_path';
        $profile_values[] = $profile_photo_path;
    }
    if (table_has_column($pdo, 'profiles', 'qr_path') && $is_digital_profile_active) {
        $profile_columns[] = 'qr_path';
        $profile_values[] = $dynamic_qr_url;
    }
    if (table_has_column($pdo, 'profiles', 'is_active')) {
        $profile_columns[] = 'is_active';
        $profile_values[] = $is_digital_profile_active ? 1 : 0;
    }

    $profile_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $profile_columns));
    $profile_placeholders = implode(', ', array_fill(0, count($profile_values), '?'));
    $stmt = $pdo->prepare("INSERT INTO profiles ({$profile_columns_sql}) VALUES ({$profile_placeholders})");
    $stmt->execute($profile_values);
    $profile_id = (int)$pdo->lastInsertId();

    $can_insert_social_links = $is_digital_profile_active
        && table_exists($pdo, 'social_links')
        && table_has_column($pdo, 'social_links', 'profile_id')
        && table_has_column($pdo, 'social_links', 'platform')
        && table_has_column($pdo, 'social_links', 'url');

    if ($can_insert_social_links && !empty($social_links)) {
        $stmt_social = $pdo->prepare('INSERT INTO social_links (profile_id, platform, url) VALUES (?, ?, ?)');
        foreach ($social_links as $link) {
            $stmt_social->execute([$profile_id, $link['platform'], $link['url']]);
        }
    }

    $pdo->commit();
    redirect_with_fallback('../auth/login.php?success=register');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = (string)$e->getMessage();
    if (str_starts_with($message, 'logo_') || str_starts_with($message, 'profile_photo_')) {
        redirect_register_error($message, 3);
    }

    if ((string)$e->getCode() === '23000') {
        redirect_register_error('email_exists', 2);
    }

    $error_text = 'register_process_error: ' . $message . ' @ ' . $e->getFile() . ':' . $e->getLine();
    error_log($error_text);
    write_register_error_log($error_text);
    redirect_register_error('register_failed', max(1, $current_step));
}
