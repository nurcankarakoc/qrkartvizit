<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/subscription.php';
require_once __DIR__ . '/core/social_branding.php';

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_e = str_replace('`', '``', $table);
    $column_e = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_e}` LIKE '{$column_e}'");
    return (bool)$stmt->fetch();
}

function normalize_hex_color(string $value): ?string
{
    $candidate = strtoupper(trim($value));
    if (!preg_match('/^#[0-9A-F]{6}$/', $candidate)) {
        return null;
    }

    return $candidate;
}

function darken_hex_color(string $hex, float $factor = 0.3): string
{
    $hex = normalize_hex_color($hex) ?? '#062626';
    $factor = min(max($factor, 0.0), 1.0);

    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));

    $r = (int)max(0, round($r * (1 - $factor)));
    $g = (int)max(0, round($g * (1 - $factor)));
    $b = (int)max(0, round($b * (1 - $factor)));

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

function normalize_initial_text(string $raw): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    if (!is_string($value) || $value === '') {
        return '';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($value, 0, 2, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($value, 0, 2));
}

function derive_initial_from_name(string $name, string $fallback = 'A'): string
{
    $clean_name = trim($name);
    if ($clean_name === '') {
        return normalize_initial_text($fallback) ?: 'A';
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

    return normalize_initial_text($letters) ?: 'A';
}

function contrast_text_for_hex(string $hex): string
{
    $normalized = normalize_hex_color($hex);
    if ($normalized === null) {
        return '#FFFFFF';
    }

    $r = hexdec(substr($normalized, 1, 2));
    $g = hexdec(substr($normalized, 3, 2));
    $b = hexdec(substr($normalized, 5, 2));
    $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return $luminance >= 160 ? '#0F172A' : '#FFFFFF';
}

function render_state_page(int $code, string $title, string $msg): void
{
    http_response_code($code);
    ?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-height: 100vh; display: grid; place-items: center; background: #f8fafc; font-family: Inter, sans-serif; padding: 2rem; }
        .box { width: min(420px, 100%); padding: 3rem 2rem; text-align: center; background: #fff; border-radius: 32px; box-shadow: 0 15px 45px rgba(0, 0, 0, .05); border: 1px solid rgba(0, 0, 0, .03); }
        .icon { width: 64px; height: 64px; margin: 0 auto 1.5rem; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #0A2F2F; background: rgba(10, 47, 47, 0.08); }
        h1 { font-size: 1.4rem; font-weight: 800; color: #0A2F2F; margin-bottom: 1rem; letter-spacing: -0.5px; }
        p { color: #64748b; line-height: 1.7; font-size: .95rem; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">i</div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo htmlspecialchars($msg); ?></p>
    </div>
</body>
</html><?php
    exit();
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || strlen($slug) > 255) {
    render_state_page(404, 'Profil Bulunamadı', 'Geçerli bir dijital kartvizit bağlantısı kullanın.');
}

$has_user_active = table_has_column($pdo, 'users', 'is_active');
$user_active_sel = $has_user_active ? ', u.is_active AS user_active' : '';

$stmt = $pdo->prepare(
    "SELECT p.*, u.name AS owner_name{$user_active_sel}
     FROM profiles p
     JOIN users u ON u.id = p.user_id
     WHERE p.slug = ? LIMIT 1"
);
$stmt->execute([$slug]);
$profile = $stmt->fetch();
if (!$profile) {
    render_state_page(404, 'Profil Bulunamadı', 'Aradığınız kartvizit sayfası mevcut değil.');
}

$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([(int)$profile['user_id']]);
$order = $stmt->fetch();
if (!qrk_user_has_digital_access($pdo, (int)$profile['user_id'], (string)($order['package'] ?? ''))) {
    render_state_page(403, 'Profil Aktif Değil', 'Bu hesapta dijital profil paketi aktif değil.');
}

$profile_active = !table_has_column($pdo, 'profiles', 'is_active') || (int)($profile['is_active'] ?? 1) === 1;
$user_active = !$has_user_active || (int)($profile['user_active'] ?? 1) === 1;
if (!$profile_active || !$user_active) {
    render_state_page(410, 'Profil Yayın Dışı', 'Bu dijital kartvizit şu an görüntülenemiyor.');
}

$social_links_has_logo = table_has_column($pdo, 'social_links', 'logo_path');
$social_links_select = $social_links_has_logo
    ? "SELECT platform,url,logo_path FROM social_links WHERE profile_id=? ORDER BY id ASC"
    : "SELECT platform,url,NULL AS logo_path FROM social_links WHERE profile_id=? ORDER BY id ASC";
$stmt = $pdo->prepare($social_links_select);
$stmt->execute([(int)$profile['id']]);
$social_links = $stmt->fetchAll();

$full_name = trim((string)($profile['full_name'] ?? '')) ?: trim((string)($profile['owner_name'] ?? 'Dijital Kartvizit'));
$title_text = trim((string)($profile['title'] ?? ''));
$company = trim((string)($profile['company'] ?? ''));
$bio = trim((string)($profile['bio'] ?? ''));
$phone_work = trim((string)($profile['phone_work'] ?? ''));
$email_work = trim((string)($profile['email_work'] ?? ''));
$photo_path = trim((string)($profile['photo_path'] ?? ''));
$brand_color = normalize_hex_color((string)($profile['brand_color'] ?? '')) ?? '#0A2F2F';
$cover_path = trim((string)($profile['cover_photo'] ?? ''));
$qr_style_payload = (string)($profile['qr_style'] ?? '');
$decoded_qr_style = [];
if ($qr_style_payload !== '') {
    $candidate_qr_style = json_decode($qr_style_payload, true);
    if (is_array($candidate_qr_style)) {
        $decoded_qr_style = $candidate_qr_style;
    }
}

$cover_color = normalize_hex_color((string)($profile['cover_color'] ?? ''));
if ($cover_color === null) {
    $cover_color = normalize_hex_color((string)($decoded_qr_style['cover_color'] ?? ''));
}
if ($cover_color === null) {
    $cover_color = $brand_color;
}
$cover_dark = darken_hex_color($cover_color, 0.34);

$avatar_initial = normalize_initial_text((string)($profile['avatar_initial'] ?? ''));
if ($avatar_initial === '') {
    $avatar_initial = normalize_initial_text((string)($decoded_qr_style['avatar_initial'] ?? ''));
}
if ($avatar_initial === '') {
    $avatar_initial = derive_initial_from_name($full_name, 'A');
}

$avatar_color = normalize_hex_color((string)($profile['avatar_color'] ?? ''));
if ($avatar_color === null) {
    $avatar_color = normalize_hex_color((string)($decoded_qr_style['avatar_color'] ?? ''));
}
if ($avatar_color === null) {
    $avatar_color = $brand_color;
}
$avatar_text_color = contrast_text_for_hex($avatar_color);

$platform_meta = [
    'instagram' => qrk_get_social_platform_meta('instagram'),
    'whatsapp' => qrk_get_social_platform_meta('whatsapp'),
    'linkedin' => qrk_get_social_platform_meta('linkedin'),
    'website' => qrk_get_social_platform_meta('website'),
    'x' => qrk_get_social_platform_meta('x'),
    'twitter' => qrk_get_social_platform_meta('twitter'),
    'youtube' => qrk_get_social_platform_meta('youtube'),
    'facebook' => qrk_get_social_platform_meta('facebook'),
    'tiktok' => qrk_get_social_platform_meta('tiktok'),
    'telegram' => qrk_get_social_platform_meta('telegram'),
    'mail' => qrk_get_social_platform_meta('mail'),
    'phone' => qrk_get_social_platform_meta('phone'),
    'maps' => qrk_get_social_platform_meta('maps'),
];

$title_line_parts = [];
if ($title_text !== '') {
    $title_line_parts[] = function_exists('mb_strtoupper') ? mb_strtoupper($title_text, 'UTF-8') : strtoupper($title_text);
}
if ($company !== '') {
    $title_line_parts[] = function_exists('mb_strtoupper') ? mb_strtoupper($company, 'UTF-8') : strtoupper($company);
}
$title_line = implode(' • ', $title_line_parts);

$bio_clean = preg_replace('/\s+/u', ' ', trim($bio));
$bio_excerpt = $bio_clean;
$has_long_bio = false;
if ($bio_clean !== '') {
    $bio_length = function_exists('mb_strlen') ? mb_strlen($bio_clean, 'UTF-8') : strlen($bio_clean);
    if ($bio_length > 150) {
        $has_long_bio = true;
        $bio_excerpt = function_exists('mb_substr')
            ? rtrim(mb_substr($bio_clean, 0, 150, 'UTF-8')) . '...'
            : rtrim(substr($bio_clean, 0, 150)) . '...';
    }
}

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
$profile_url = ($is_https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
$profile_qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&ecc=M&data=' . rawurlencode($profile_url);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --brand: <?php echo $brand_color; ?>;
            --accent: #A6803F;
            --accent-light: #D6B46A;
            --accent-glow: rgba(166, 128, 63, 0.35);
            --navy: #0A2F2F;
            --navy-mid: #0d3d3d;
            --navy-dark: #061e1e;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        html, body { max-width: 100%; overflow-x: hidden; }
        body {
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: #f8fafc;
            -webkit-font-smoothing: antialiased;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            position: relative;
        }

        <?php if ($cover_path !== ''): ?>
        body {
            background: linear-gradient(180deg, rgba(7,36,36,0.85), rgba(10,47,47,0.95)),
                url('<?php echo htmlspecialchars($cover_path); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        <?php else: ?>
        body {
            background: linear-gradient(135deg, #071f1f 0%, var(--navy) 40%, var(--navy-mid) 100%);
        }
        <?php endif; ?>

        body::before {
            content: ''; position: absolute; inset: 0; z-index: -1;
            background: radial-gradient(circle at 50% 10%, rgba(166,128,63,0.15) 0%, transparent 55%),
                        radial-gradient(circle at 50% 90%, rgba(10,47,47,0.5) 0%, transparent 60%);
            pointer-events: none;
        }

        /* ── Cover ── */
        .cover-section { display: none; }

        .card-container {
            width: min(100%, 420px);
            margin: 12vh auto 40px;
            padding: 0 16px 40px;
            position: relative;
            z-index: 10;
        }
        .card-main {
            background: rgba(10, 47, 47, 0.4);
            border-radius: 36px;
            box-shadow: 0 30px 70px rgba(0,0,0,0.3), inset 0 0 0 1px rgba(166,128,63,0.25);
            border: 1px solid rgba(166,128,63,0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            overflow: visible;
            animation: cardSlideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes cardSlideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Profile ── */
        .profile-header {
            text-align: center;
            padding: 0 28px 28px;
        }
        .avatar-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            margin: -55px auto 20px;
        }
        .avatar-wrapper::before {
            content: '';
            position: absolute;
            inset: -15px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--accent-glow), transparent 70%);
            filter: blur(10px);
            z-index: 0;
        }
        .avatar-ring {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: conic-gradient(from 0deg, #F7E9BF, var(--accent), #D6B46A, var(--accent), #F7E9BF);
            z-index: 1;
        }
        @keyframes ringShimmer {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .avatar-inner {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid var(--accent);
            box-shadow: 0 0 20px var(--accent-glow);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 700;
            color: var(--accent);
            z-index: 2;
        }
        .avatar-inner.avatar--monogram {
            background: var(--avatar-bg, var(--accent));
            color: var(--avatar-fg, #fff);
        }
        .avatar-inner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-family: 'Outfit', sans-serif;
            font-size: 2.1rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            line-height: 1.15;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .profile-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-top: 10px;
        }
        .profile-bio {
            font-size: 0.95rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.7);
            margin-top: 20px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }
        .profile-bio.full { display: none; }
        .bio-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
            border: 0;
            background: transparent;
            color: var(--accent);
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .bio-toggle-btn:hover { color: var(--accent-light); }

        /* ── Actions ── */
        .actions-bar {
            display: flex;
            gap: 12px;
            padding: 0 16px;
            margin-top: 32px;
            justify-content: center;
        }
        .btn-action-icon {
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.04);
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-action-icon:hover {
            border-color: rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.08);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-cta-main {
            flex: 1;
            max-width: 260px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(166,128,63,0.95) 0%, rgba(166,128,63,0.7) 100%);
            border: 1px solid rgba(166,128,63,0.4);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(166,128,63,0.25);
        }
        .btn-cta-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(166,128,63,0.4);
            border-color: rgba(166,128,63,0.8);
        }

        /* ── Divider ── */
        .section-divider {
            display: none;
        }

        /* ── Contacts ── */
        .contacts-section {
            padding: 0 16px 28px;
            margin-top: 36px;
        }
        .contacts-label { display: none; }
        .contacts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .contact-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 16px 12px;
            border-radius: 16px;
            background: rgba(10,47,47,0.3);
            border: 1px solid rgba(166,128,63,0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            text-decoration: none;
            color: #fff;
            transition: all 0.3s ease;
        }
        .contact-tile:hover {
            background: rgba(10,47,47,0.6);
            border-color: rgba(166,128,63,0.4);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(166,128,63,0.15), inset 0 0 0 1px rgba(166,128,63,0.2);
        }
        .tile-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
        }
        .tile-icon img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }
        .tile-icon i, .tile-icon svg {
            width: 22px;
            height: 22px;
            color: rgba(255,255,255,0.9);
        }
        /* E-posta, telefon is blue/orange in screenshot. If we want them fully colored, we can colorize i tags too */
        .tile-icon i { color: #fff; }
        
        .tile-text strong {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(255,255,255,0.95);
        }
        .tile-text span {
            display: none; /* Hide link values exactly like screenshots */
        }
        .contact-tile--social .tile-text span { text-transform: lowercase; }

        /* ── Footer ── */
        .card-footer {
            text-align: center;
            padding: 30px 20px;
            background: transparent;
            border-top: none;
            opacity: 0.6;
        }
        .footer-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            color: rgba(255,255,255,0.6);
            letter-spacing: 0.15em;
            text-transform: uppercase;
            text-decoration: none;
            transition: color 0.3s;
        }
        .footer-badge:hover { color: #fff; }
        .footer-badge i { width: 14px; height: 14px; color: var(--accent); }

        /* ── QR Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10,20,30,0.45);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 100;
        }
        .modal-overlay.is-open { display: flex; }
        .modal-box {
            width: min(100%, 380px);
            padding: 32px;
            border-radius: 28px;
            background: #fff;
            box-shadow: 0 24px 64px rgba(0,0,0,0.18);
            text-align: center;
            animation: modalIn 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(16px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-box h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: #0c1829;
            margin-bottom: 6px;
        }
        .modal-box p {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .modal-box img {
            width: 100%;
            max-width: 200px;
            margin: 0 auto 24px;
            border-radius: 20px;
            border: 1px solid #eef2f6;
            padding: 14px;
            background: #fff;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .modal-close-btn {
            width: 100%;
            height: 48px;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .modal-close-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .card-container { padding: 40px 14px 40px; }
            .avatar-wrapper { width: 100px; height: 100px; margin-bottom: 20px; }
            .profile-name { font-size: 1.8rem; }
            .profile-header { padding: 0 10px 20px; }
            .actions-bar { padding: 0 10px; }
            .contacts-section { padding: 0 10px 20px; }
            .contacts-grid { gap: 12px; }
            .contact-tile { padding: 18px 12px; }
        }
        @media (max-width: 360px) {
            .contacts-grid { grid-template-columns: 1fr; }
            .modal-box { padding: 20px 16px; }
        }
    </style>
</head>
<body>
    <!-- Cover -->
    <div class="cover-section"></div>

    <!-- Card -->
    <div class="card-container">
        <div class="card-main">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="avatar-wrapper">
                    <div class="avatar-ring">
                        <div class="avatar-inner<?php echo $photo_path ? '' : ' avatar--monogram'; ?>" <?php if (!$photo_path): ?>style="--avatar-bg: <?php echo htmlspecialchars($avatar_color); ?>; --avatar-fg: <?php echo htmlspecialchars($avatar_text_color); ?>;"<?php endif; ?>>
                            <?php if ($photo_path): ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                            <?php else: ?>
                                <?php echo htmlspecialchars($avatar_initial); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($full_name); ?></h1>
                <?php if ($title_line !== ''): ?>
                    <p class="profile-title"><?php echo htmlspecialchars($title_line); ?></p>
                <?php endif; ?>
                <?php if ($bio_clean !== ''): ?>
                    <p class="profile-bio excerpt" id="bioExcerpt"><?php echo htmlspecialchars($bio_excerpt); ?></p>
                    <?php if ($has_long_bio): ?>
                        <p class="profile-bio full" id="bioFull"><?php echo htmlspecialchars($bio_clean); ?></p>
                        <button class="bio-toggle-btn" type="button" id="bioToggle">
                            <span>Devamını Oku</span>
                            <i data-lucide="chevron-down"></i>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <button type="button" class="btn-action-icon" id="openQrButton" aria-label="QR kodu göster">
                    <i data-lucide="qr-code"></i>
                </button>
                <a href="processes/generate_vcard.php?slug=<?php echo urlencode($slug); ?>" class="btn-cta-main">
                    <i data-lucide="user-plus"></i>
                    <span>Rehbere Ekle</span>
                </a>
                <button type="button" class="btn-action-icon" id="shareButton" aria-label="Kartviziti paylaş">
                    <i data-lucide="share-2"></i>
                </button>
            </div>

            <!-- Divider -->
            <div class="section-divider"></div>

            <!-- Contacts -->
            <div class="contacts-section">
                <div class="contacts-label">İletişim</div>
                <div class="contacts-grid">
                    <?php if ($phone_work !== ''): ?>
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone_work); ?>" class="contact-tile">
                            <div class="tile-icon"><i data-lucide="phone"></i></div>
                            <div class="tile-text">
                                <strong>Telefon</strong>
                                <span><?php echo htmlspecialchars($phone_work); ?></span>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php if ($email_work !== ''): ?>
                        <a href="mailto:<?php echo htmlspecialchars($email_work); ?>" class="contact-tile">
                            <div class="tile-icon">
                                <img src="<?php echo htmlspecialchars((string)qrk_get_social_platform_meta('mail')['logo']); ?>" alt="E-posta" loading="lazy">
                            </div>
                            <div class="tile-text">
                                <strong>E-posta</strong>
                                <span><?php echo htmlspecialchars($email_work); ?></span>
                            </div>
                        </a>
                    <?php endif; ?>
                    <?php foreach ($social_links as $link): ?>
                        <?php
                        $platform_key = strtolower(trim((string)($link['platform'] ?? '')));
                        $meta = $platform_meta[$platform_key] ?? qrk_get_social_platform_meta('__custom__');
                        $label = (string)($meta['label'] ?? ucwords(str_replace(['_', '-'], ' ', $platform_key)));
                        $link_url = trim((string)($link['url'] ?? ''));
                        if ($link_url === '') { continue; }
                        $custom_logo = trim((string)($link['logo_path'] ?? ''));
                        $custom_logo = preg_match('#^assets/uploads/social_logos/[A-Za-z0-9._-]+$#', $custom_logo) ? $custom_logo : '';
                        $logo_url = $custom_logo !== '' ? $custom_logo : (string)($meta['logo'] ?? '');
                        $link_host = parse_url($link_url, PHP_URL_HOST);
                        $link_host = is_string($link_host) ? preg_replace('/^www\./i', '', $link_host) : '';
                        ?>
                        <a href="<?php echo htmlspecialchars($link_url); ?>" target="_blank" rel="noopener noreferrer" class="contact-tile contact-tile--social">
                            <div class="tile-icon">
                                <?php if ($logo_url !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($label); ?>" loading="lazy" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <i data-lucide="globe"></i>
                                <?php endif; ?>
                            </div>
                            <div class="tile-text">
                                <strong><?php echo htmlspecialchars($label); ?></strong>
                                <span><?php echo htmlspecialchars($link_host !== '' ? $link_host : $link_url); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="card-footer">
                <a href="https://zerosoft.com.tr" class="footer-badge">
                    <i data-lucide="sparkles"></i> Zerosoft Digital
                </a>
            </div>
        </div>
    </div>

    <!-- QR Modal -->
    <div class="modal-overlay" id="qrModal" aria-hidden="true">
        <div class="modal-box">
            <h2>QR ile Aç</h2>
            <p>Bu kartviziti telefon kamerasıyla hızlıca açmak için QR kodu taratın.</p>
            <img src="<?php echo htmlspecialchars($profile_qr_url); ?>" alt="Kartvizit QR kodu">
            <button type="button" class="modal-close-btn" id="closeQrButton">Kapat</button>
        </div>
    </div>
    <script>
        lucide.createIcons();

        const bioToggle = document.getElementById('bioToggle');
        const bioExcerpt = document.getElementById('bioExcerpt');
        const bioFull = document.getElementById('bioFull');
        if (bioToggle && bioExcerpt && bioFull) {
            bioToggle.addEventListener('click', function () {
                const expanded = bioFull.style.display === 'block';
                bioFull.style.display = expanded ? 'none' : 'block';
                bioExcerpt.style.display = expanded ? 'block' : 'none';
                bioToggle.querySelector('span').textContent = expanded ? 'Devamını Oku' : 'Daha Az Göster';
                const icon = bioToggle.querySelector('svg');
                if (icon) {
                    icon.style.transform = expanded ? 'rotate(0deg)' : 'rotate(180deg)';
                    icon.style.transition = 'transform .2s ease';
                }
            });
        }

        const qrModal = document.getElementById('qrModal');
        const openQrButton = document.getElementById('openQrButton');
        const closeQrButton = document.getElementById('closeQrButton');
        if (qrModal && openQrButton && closeQrButton) {
            const closeQrModal = function () {
                qrModal.classList.remove('is-open');
                qrModal.setAttribute('aria-hidden', 'true');
            };
            openQrButton.addEventListener('click', function () {
                qrModal.classList.add('is-open');
                qrModal.setAttribute('aria-hidden', 'false');
            });
            closeQrButton.addEventListener('click', closeQrModal);
            qrModal.addEventListener('click', function (event) {
                if (event.target === qrModal) {
                    closeQrModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeQrModal();
                }
            });
        }

        const shareButton = document.getElementById('shareButton');
        if (shareButton) {
            shareButton.addEventListener('click', async function () {
                const shareData = {
                    title: <?php echo json_encode($full_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    text: <?php echo json_encode($title_text !== '' ? $title_text : 'Dijital kartvizit', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    url: <?php echo json_encode($profile_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                };
                try {
                    if (navigator.share) {
                        await navigator.share(shareData);
                        return;
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(shareData.url);
                        shareButton.title = 'Bağlantı kopyalandı';
                        setTimeout(function () {
                            shareButton.title = 'Kartviziti paylaş';
                        }, 1800);
                    } else {
                        window.prompt('Bağlantıyı kopyalayın:', shareData.url);
                    }
                } catch (error) {
                }
            });
        }
    </script>
</body>
</html>
