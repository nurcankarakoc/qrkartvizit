<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../auth/login.php");
    exit();
}

verify_csrf_or_redirect('../customer/profile.php?error=csrf');

function table_has_column(PDO $pdo, string $table, string $column): bool
{
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
        if ($digits === null || $digits === '') {
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

    $platform = str_replace(['twitter', 'x-twitter'], 'x', $platform);
    $platform = str_replace(['ı', 'İ', 'ş', 'Ş', 'ç', 'Ç', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö'], ['i', 'i', 's', 's', 'c', 'c', 'g', 'g', 'u', 'u', 'o', 'o'], $platform);
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

$user_id = (int)$_SESSION['user_id'];
$full_name = trim((string)($_POST['full_name'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$phone_work = trim((string)($_POST['phone_work'] ?? ''));
$email_work = trim((string)($_POST['email_work'] ?? ''));
$bio = trim((string)($_POST['bio'] ?? ''));

if ($email_work !== '' && !filter_var($email_work, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../customer/profile.php?error=invalid_email");
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$profile_id = $stmt->fetchColumn();

if (!$profile_id) {
    echo "Profil bulunamadi.";
    exit();
}

$sql = "UPDATE profiles SET full_name = ?, title = ?, company = ?, phone_work = ?, email_work = ?, bio = ?";
$params = [$full_name, $title, $company, $phone_work, $email_work, $bio];

$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();
$is_digital_profile_active_for_package = has_digital_profile_package((string)($order['package'] ?? ''));

if ($is_digital_profile_active_for_package && table_has_column($pdo, 'profiles', 'is_active')) {
    $sql .= ", is_active = 1";
}

if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $upload_dir = '../assets/uploads/profiles/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        header("Location: ../customer/profile.php?error=upload_dir");
        exit();
    }

    if (($_FILES['photo']['size'] ?? 0) > 5 * 1024 * 1024) {
        header("Location: ../customer/profile.php?error=file_size");
        exit();
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['photo']['tmp_name']);
    if (!isset($allowed_mimes[$mime])) {
        header("Location: ../customer/profile.php?error=file_type");
        exit();
    }

    $file_name = 'profile_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = 'assets/uploads/profiles/' . $file_name;
        $sql .= ", photo_path = ?";
        $params[] = $photo_path;
    }
}

$sql .= " WHERE id = ?";
$params[] = (int)$profile_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$stmt = $pdo->prepare("DELETE FROM social_links WHERE profile_id = ?");
$stmt->execute([(int)$profile_id]);

if (isset($_POST['platforms']) && is_array($_POST['platforms'])) {
    $insert_stmt = $pdo->prepare("INSERT INTO social_links (profile_id, platform, url) VALUES (?, ?, ?)");
    $custom_platforms = isset($_POST['platform_customs']) && is_array($_POST['platform_customs'])
        ? $_POST['platform_customs']
        : [];

    foreach ($_POST['platforms'] as $index => $platform_raw) {
        $selected_platform = strtolower(trim((string)$platform_raw));
        $platform_candidate = $selected_platform === '__custom__'
            ? (string)($custom_platforms[$index] ?? '')
            : $selected_platform;

        $platform = normalize_platform_key($platform_candidate);
        if ($platform === null) {
            continue;
        }

        $url = normalize_social_url($platform, (string)($_POST['urls'][$index] ?? ''));
        if ($url === null) {
            continue;
        }

        $insert_stmt->execute([(int)$profile_id, $platform, $url]);
    }
}

header("Location: ../customer/profile.php?success=1");
exit();
?>
