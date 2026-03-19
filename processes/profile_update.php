<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

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

function has_digital_profile_package(?string $package): bool
{
    if (!is_string($package) || $package === '') {
        return false;
    }
    $normalized = strtolower(trim($package));
    return str_contains($normalized, 'panel')
        || str_contains($normalized, 'smart')
        || str_contains($normalized, 'akilli');
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
$brand_color = preg_match('/^#[0-9a-fA-F]{6}$/', $brand_color_raw) ? strtoupper($brand_color_raw) : null;

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
$is_digital_profile_active = has_digital_profile_package((string)($order['package'] ?? ''));

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
if (table_has_column($pdo, 'profiles', 'theme_color')) {
    $update_fields[] = 'theme_color = ?';
    $update_params[] = $brand_color;
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
