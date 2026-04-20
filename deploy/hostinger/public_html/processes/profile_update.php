<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/subscription.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header("Location: ../auth/login.php");
    exit();
}

verify_csrf_or_redirect('../customer/profile.php?error=csrf');

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

function normalize_hex_color(string $raw): ?string
{
    $value = strtoupper(trim($raw));
    if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
        return null;
    }
    return $value;
}

function normalize_palette_json(string $raw_palette, ?string $fallback_color = null): ?string
{
    $decoded = json_decode($raw_palette, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $palette = [];
    foreach ($decoded as $item) {
        $color = normalize_hex_color((string)$item);
        if ($color === null) {
            continue;
        }
        if (!in_array($color, $palette, true)) {
            $palette[] = $color;
        }
        if (count($palette) >= 6) {
            break;
        }
    }

    if ($fallback_color !== null && !in_array($fallback_color, $palette, true)) {
        array_unshift($palette, $fallback_color);
        $palette = array_slice(array_values(array_unique($palette)), 0, 6);
    }

    if (empty($palette)) {
        return null;
    }

    return json_encode($palette, JSON_UNESCAPED_UNICODE);
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

function process_image_upload(int $user_id, string $file_key, string $prefix): ?string
{
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($_FILES[$file_key]) || ($_FILES[$file_key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($_FILES[$file_key]['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $tmp_name = (string)($_FILES[$file_key]['tmp_name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);
    if (!isset($allowed_mimes[$mime])) {
        return null;
    }

    $upload_dir = '../assets/uploads/profiles/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return null;
    }

    $file_name = $prefix . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $target = $upload_dir . $file_name;
    if (!move_uploaded_file($tmp_name, $target)) {
        return null;
    }

    return 'assets/uploads/profiles/' . $file_name;
}

$user_id = (int)$_SESSION['user_id'];
$full_name = trim((string)($_POST['full_name'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$phone_work = trim((string)($_POST['phone_work'] ?? ''));
$email_work = trim((string)($_POST['email_work'] ?? ''));
$bio = trim((string)($_POST['bio'] ?? ''));
$brand_color_raw = trim((string)($_POST['brand_color'] ?? ''));
$brand_color = normalize_hex_color($brand_color_raw);
$cover_color = normalize_hex_color((string)($_POST['cover_color'] ?? ''));
$resolved_cover_color = $cover_color ?? $brand_color ?? '#0A2F2F';
$avatar_color = normalize_hex_color((string)($_POST['avatar_color'] ?? ''));
$resolved_avatar_color = $avatar_color ?? $brand_color ?? '#0A2F2F';
$default_avatar_initial = derive_initial_from_name($full_name, 'A');
$avatar_initial = normalize_initial_text((string)($_POST['avatar_initial'] ?? ''), $default_avatar_initial);
$brand_palette = normalize_palette_json((string)($_POST['brand_palette'] ?? ''), $brand_color);
$qr_color = normalize_hex_color((string)($_POST['qr_color'] ?? ''));
$qr_bg_color = normalize_hex_color((string)($_POST['qr_bg_color'] ?? ''));
$qr_dot_style = normalize_enum((string)($_POST['qr_dot_style'] ?? ''), ['square', 'dots', 'rounded', 'classy', 'classy-rounded', 'extra-rounded'], 'square');
$qr_corner_style = normalize_enum((string)($_POST['qr_corner_style'] ?? ''), ['square', 'dot', 'extra-rounded'], 'square');
$qr_frame_style = normalize_enum((string)($_POST['qr_frame_style'] ?? ''), ['classic', 'soft', 'badge', 'none'], 'classic');
$qr_style_payload = json_encode([
    'qr_color' => $qr_color ?? $brand_color ?? '#0A2F2F',
    'qr_bg_color' => $qr_bg_color ?? '#FFFFFF',
    'qr_dot_style' => $qr_dot_style,
    'qr_corner_style' => $qr_corner_style,
    'qr_frame_style' => $qr_frame_style,
    'cover_color' => $resolved_cover_color,
    'avatar_initial' => $avatar_initial,
    'avatar_color' => $resolved_avatar_color,
], JSON_UNESCAPED_UNICODE);

if ($email_work !== '' && !filter_var($email_work, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../customer/profile.php?error=invalid_email");
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$profile_id = (int)($stmt->fetchColumn() ?: 0);

if ($profile_id <= 0) {
    header("Location: ../customer/profile.php?error=profile_not_found");
    exit();
}

$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();
$is_digital_profile_active = qrk_user_has_digital_access($pdo, $user_id, (string)($order['package'] ?? ''));

$update_fields = [];
$update_params = [];

if (table_has_column($pdo, 'profiles', 'full_name')) {
    $update_fields[] = 'full_name = ?';
    $update_params[] = $full_name;
}
if (table_has_column($pdo, 'profiles', 'title')) {
    $update_fields[] = 'title = ?';
    $update_params[] = $title;
}
if (table_has_column($pdo, 'profiles', 'company')) {
    $update_fields[] = 'company = ?';
    $update_params[] = $company;
}
if (table_has_column($pdo, 'profiles', 'phone_work')) {
    $update_fields[] = 'phone_work = ?';
    $update_params[] = $phone_work;
}
if (table_has_column($pdo, 'profiles', 'email_work')) {
    $update_fields[] = 'email_work = ?';
    $update_params[] = $email_work;
}
if (table_has_column($pdo, 'profiles', 'bio')) {
    $update_fields[] = 'bio = ?';
    $update_params[] = $bio;
}
if (table_has_column($pdo, 'profiles', 'brand_color')) {
    $update_fields[] = 'brand_color = ?';
    $update_params[] = $brand_color;
}
if (table_has_column($pdo, 'profiles', 'brand_palette')) {
    $update_fields[] = 'brand_palette = ?';
    $update_params[] = $brand_palette;
}
if (table_has_column($pdo, 'profiles', 'theme_color')) {
    $update_fields[] = 'theme_color = ?';
    $update_params[] = $brand_color;
}
if (table_has_column($pdo, 'profiles', 'cover_color')) {
    $update_fields[] = 'cover_color = ?';
    $update_params[] = $resolved_cover_color;
}
if (table_has_column($pdo, 'profiles', 'avatar_initial')) {
    $update_fields[] = 'avatar_initial = ?';
    $update_params[] = $avatar_initial;
}
if (table_has_column($pdo, 'profiles', 'avatar_color')) {
    $update_fields[] = 'avatar_color = ?';
    $update_params[] = $resolved_avatar_color;
}
if (table_has_column($pdo, 'profiles', 'qr_color')) {
    $update_fields[] = 'qr_color = ?';
    $update_params[] = $qr_color ?? $brand_color;
}
if (table_has_column($pdo, 'profiles', 'qr_bg_color')) {
    $update_fields[] = 'qr_bg_color = ?';
    $update_params[] = $qr_bg_color ?? '#FFFFFF';
}
if (table_has_column($pdo, 'profiles', 'qr_dot_style')) {
    $update_fields[] = 'qr_dot_style = ?';
    $update_params[] = $qr_dot_style;
}
if (table_has_column($pdo, 'profiles', 'qr_corner_style')) {
    $update_fields[] = 'qr_corner_style = ?';
    $update_params[] = $qr_corner_style;
}
if (table_has_column($pdo, 'profiles', 'qr_frame_style')) {
    $update_fields[] = 'qr_frame_style = ?';
    $update_params[] = $qr_frame_style;
}
if (table_has_column($pdo, 'profiles', 'qr_style')) {
    $update_fields[] = 'qr_style = ?';
    $update_params[] = $qr_style_payload;
}
if ($is_digital_profile_active && table_has_column($pdo, 'profiles', 'is_active')) {
    $update_fields[] = 'is_active = 1';
}

$photo_path = process_image_upload($user_id, 'photo', 'profile_');
if ($photo_path !== null && table_has_column($pdo, 'profiles', 'photo_path')) {
    $update_fields[] = 'photo_path = ?';
    $update_params[] = $photo_path;
}

$cover_path = process_image_upload($user_id, 'cover_photo', 'cover_');
if ($cover_path !== null && table_has_column($pdo, 'profiles', 'cover_photo')) {
    $update_fields[] = 'cover_photo = ?';
    $update_params[] = $cover_path;
}

if (!empty($update_fields)) {
    $update_params[] = $profile_id;
    $sql = "UPDATE profiles SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_params);
}

if (table_exists($pdo, 'social_links')) {
    $stmt = $pdo->prepare("DELETE FROM social_links WHERE profile_id = ?");
    $stmt->execute([$profile_id]);

    $platforms = $_POST['platforms'] ?? [];
    $urls = $_POST['urls'] ?? [];
    $custom_platforms = $_POST['platform_customs'] ?? [];
    if (is_array($platforms) && is_array($urls)) {
        if (!is_array($custom_platforms)) {
            $custom_platforms = [];
        }

        $stmt_insert = $pdo->prepare("INSERT INTO social_links (profile_id, platform, url) VALUES (?, ?, ?)");
        foreach ($platforms as $index => $platform_raw) {
            $selected_platform = strtolower(trim((string)$platform_raw));
            $platform_candidate = $selected_platform === '__custom__'
                ? (string)($custom_platforms[$index] ?? '')
                : $selected_platform;

            $platform = normalize_platform_key($platform_candidate);
            if ($platform === null) {
                continue;
            }

            $url = normalize_social_url($platform, (string)($urls[$index] ?? ''));
            if ($url === null) {
                continue;
            }

            $stmt_insert->execute([$profile_id, $platform, $url]);
        }
    }
}

header("Location: ../customer/profile.php?success=1");
exit();
?>
