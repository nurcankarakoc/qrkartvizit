<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/subscription.php';
require_once '../core/customer_access.php';
require_once '../core/social_branding.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

function project_base_url_for_customer_panel(): string
{
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/customer/profile.php');
    $project_path = preg_replace('#/customer/[^/]+$#', '', $script_name);

    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

function darken_hex_color(string $hex, float $factor = 0.28): string
{
    $hex = strtoupper(trim($hex));
    if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
        return '#062626';
    }

    $factor = min(max($factor, 0.0), 1.0);
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));

    $r = (int)max(0, round($r * (1 - $factor)));
    $g = (int)max(0, round($g * (1 - $factor)));
    $b = (int)max(0, round($b * (1 - $factor)));

    return sprintf('#%02X%02X%02X', $r, $g, $b);
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

function contrast_text_for_hex(string $hex): string
{
    $hex = strtoupper(trim($hex));
    if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
        return '#FFFFFF';
    }

    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

    return $luminance >= 160 ? '#0F172A' : '#FFFFFF';
}

$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Mevcut profil bilgilerini cek
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    // Profil yoksa taslak oluştur — çakışmayan benzersiz slug üret
    $seed_name = (string)($user['name'] ?? ($_SESSION['user_name'] ?? 'user'));
    $slug_base = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $seed_name), '-'));
    if ($slug_base === '') {
        $slug_base = 'user';
    }

    // Race condition'a karşı: retry ile benzersiz slug bul
    $slug = '';
    $slug_check = $pdo->prepare('SELECT 1 FROM profiles WHERE slug = ? LIMIT 1');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = $slug_base . '-' . random_int(1000, 99999);
        $slug_check->execute([$candidate]);
        if (!$slug_check->fetchColumn()) {
            $slug = $candidate;
            break;
        }
    }
    if ($slug === '') {
        // Son çare: kullanıcı ID + zaman damgası ile garantili benzersizlik
        $slug = $slug_base . '-' . $user_id . '-' . time();
    }

    $stmt = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $slug, $seed_name !== '' ? $seed_name : 'User']);

    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$pdo->lastInsertId()]);
    $profile = $stmt->fetch();
}

// Sosyal medya linklerini cek
$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();
$package_state = qrk_get_customer_package_state($pdo, $user_id, (string)($order['package'] ?? ''));
$has_active_package = (string)($package_state['package_slug'] ?? '') !== '';
$pending_package_slug = (string)($package_state['pending_package_slug'] ?? '');
$pending_package_mode = (string)($package_state['pending_package_mode'] ?? '');
$pending_package_definition = (array)($package_state['pending_definition'] ?? qrk_get_unknown_package_definition());
$is_preview_mode = !$has_active_package && $pending_package_slug !== '' && $pending_package_mode === 'preview';
$current_package_definition = $has_active_package ? $package_state['definition'] : ($is_preview_mode ? $pending_package_definition : $package_state['definition']);
$resolved_package_for_access = $has_active_package
    ? (string)$package_state['package_slug']
    : ($is_preview_mode ? $pending_package_slug : (string)($order['package'] ?? ''));
$is_digital_profile_active_for_package = qrk_user_has_digital_access($pdo, $user_id, $resolved_package_for_access);
$package_summary_label = $is_preview_mode ? 'İnceleme Paketi' : 'Aktif Paket';
$package_status_text = $is_preview_mode
    ? 'İnceleme modu açık. Kaydettiğinizde satın alma hazırlığına yönlendirilirsiniz.'
    : ($is_digital_profile_active_for_package ? 'Dijital profil modülü aktif' : 'Bu pakette dijital profil modülü kapalı');
$package_status_bg = $is_preview_mode ? '#eff6ff' : ($is_digital_profile_active_for_package ? '#ecfdf5' : '#fff7ed');
$package_status_color = $is_preview_mode ? '#1d4ed8' : ($is_digital_profile_active_for_package ? '#166534' : '#9a3412');
$is_panel_package = ($resolved_package_for_access === 'panel');
$show_profile_qr_customization = $is_digital_profile_active_for_package && $is_panel_package;
$lock_profile_qr_to_print_design = $is_digital_profile_active_for_package && !$is_panel_package;
$profile_slug = trim((string)($profile['slug'] ?? ''));
$public_profile_url = '';

if ($is_digital_profile_active_for_package && $profile_slug !== '') {
    $public_profile_url = project_base_url_for_customer_panel()
        . '/kartvizit.php?slug='
        . rawurlencode($profile_slug);
}

$stmt = $pdo->prepare("SELECT * FROM social_links WHERE profile_id = ?");
$stmt->execute([$profile['id']]);
$links = $stmt->fetchAll();

$social_platform_options = qrk_get_social_platform_options();

$social_platform_values = array_column($social_platform_options, 'value');
$resolved_brand_color = '#0A2F2F';
if (!empty($profile['brand_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['brand_color'])) {
    $resolved_brand_color = strtoupper((string)$profile['brand_color']);
} elseif (!empty($profile['theme_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['theme_color'])) {
    $resolved_brand_color = strtoupper((string)$profile['theme_color']);
}

$resolved_brand_palette = [$resolved_brand_color];
$palette_raw = (string)($profile['brand_palette'] ?? '');
if ($palette_raw !== '') {
    $palette_candidate = json_decode($palette_raw, true);
    if (is_array($palette_candidate)) {
        $clean_palette = [];
        foreach ($palette_candidate as $palette_color) {
            $palette_color = strtoupper(trim((string)$palette_color));
            if (!preg_match('/^#[0-9A-F]{6}$/', $palette_color)) {
                continue;
            }
            if (!in_array($palette_color, $clean_palette, true)) {
                $clean_palette[] = $palette_color;
            }
            if (count($clean_palette) >= 6) {
                break;
            }
        }
        if (!empty($clean_palette)) {
            $resolved_brand_palette = $clean_palette;
        }
    }
}

$default_qr_style = [
    'qr_color' => $resolved_brand_color,
    'qr_bg_color' => '#FFFFFF',
    'qr_dot_style' => 'square',
    'qr_corner_style' => 'square',
    'qr_frame_style' => 'classic',
];

$qr_style_payload = (string)($profile['qr_style'] ?? '');
if ($qr_style_payload !== '') {
    $decoded_qr_style = json_decode($qr_style_payload, true);
    if (is_array($decoded_qr_style)) {
        $default_qr_style = array_merge($default_qr_style, $decoded_qr_style);
    }
}

if (!empty($profile['qr_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['qr_color'])) {
    $default_qr_style['qr_color'] = strtoupper((string)$profile['qr_color']);
}
if (!empty($profile['qr_bg_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['qr_bg_color'])) {
    $default_qr_style['qr_bg_color'] = strtoupper((string)$profile['qr_bg_color']);
}
if (!empty($profile['qr_dot_style'])) {
    $default_qr_style['qr_dot_style'] = (string)$profile['qr_dot_style'];
}
if (!empty($profile['qr_corner_style'])) {
    $default_qr_style['qr_corner_style'] = (string)$profile['qr_corner_style'];
}
if (!empty($profile['qr_frame_style'])) {
    $default_qr_style['qr_frame_style'] = (string)$profile['qr_frame_style'];
}

$resolved_cover_color = $resolved_brand_color;
if (!empty($profile['cover_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['cover_color'])) {
    $resolved_cover_color = strtoupper((string)$profile['cover_color']);
}
$resolved_cover_dark = darken_hex_color($resolved_cover_color, 0.34);

$resolved_text_color = '#FFFFFF';
if (!empty($profile['text_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['text_color'])) {
    $resolved_text_color = strtoupper((string)$profile['text_color']);
} else {
    $resolved_text_color = contrast_text_for_hex($resolved_cover_color);
}

$resolved_avatar_color = $resolved_text_color;
$resolved_avatar_text_color = $resolved_cover_color;
$resolved_avatar_initial = derive_initial_from_name((string)($profile['full_name'] ?? ($user['name'] ?? '')), 'A');
if (!empty($profile['avatar_initial'])) {
    $resolved_avatar_initial = normalize_initial_text((string)$profile['avatar_initial'], $resolved_avatar_initial);
} elseif (!empty($default_qr_style['avatar_initial'])) {
    $resolved_avatar_initial = normalize_initial_text((string)$default_qr_style['avatar_initial'], $resolved_avatar_initial);
}

$success_key = trim((string)($_GET['success'] ?? ''));
$error_key = trim((string)($_GET['error'] ?? ''));
$profile_success_messages = [
    '1' => 'Profil bilgileriniz başarıyla güncellendi.',
    'preview_mode' => 'İnceleme modu açıldı. Düzenlemelerinizi gözden geçirebilir, kaydetme anında satın alma hazırlığına geçebilirsiniz.',
];
$profile_error_messages = [
    'csrf' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.',
    'invalid_email' => 'Geçerli bir iş e-posta adresi girin.',
    'profile_not_found' => 'Profil kaydı bulunamadı. Lütfen tekrar giriş yapın.',
];
$profile_success_message = $profile_success_messages[$success_key] ?? '';
$profile_error_message = $profile_error_messages[$error_key] ?? '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilimi Düzenle - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* CP Studio Color Picker Styles */
        .cp-studio-box {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 0.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        .cp-mini-picker-wrap {
            position: relative;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.22s ease;
            outline: 1px solid #dbe4ef;
        }
        .cp-mini-picker-wrap:hover {
            transform: scale(1.05);
            outline-color: var(--gold);
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
        .cp-hex-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .cp-hint {
            font-size: 0.65rem;
            color: #94a3b8;
            font-weight: 700;
        }
        .cp-hex-input {
            margin: 0 !important;
            font-family: ui-monospace, monospace !important;
            font-weight: 800 !important;
            height: 38px !important;
            border-radius: 10px !important;
            font-size: 0.9rem !important;
            padding-left: 0.8rem !important;
            border-color: #dbe4ef !important;
            background: #fff !important;
        }
        .profile-hero { position: relative; margin-bottom: 5.5rem; z-index: 10; }
        .cover-upload { width: 100%; height: 240px; border-radius: 24px; background: linear-gradient(135deg, var(--cover-dark, #062626), var(--cover-base, var(--navy-dark))); border: 2px dashed rgba(255, 255, 255, 0.15); overflow: hidden; cursor: pointer; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 40px rgba(10, 47, 47, 0.1); }
        .cover-upload::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at center, rgba(166,128,63,0.1) 0%, transparent 60%); z-index: 1; }
        .cover-upload::after { content: ''; position: absolute; inset: 0; opacity: 0.05; background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='1' fill-rule='evenodd'%3E%3Ccircle cx='3' cy='3' r='1'/%3E%3C/g%3E%3C/svg%3E"); z-index: 1; pointer-events: none; }
        .cover-upload img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; z-index: 2; border-radius: 22px; }
        .cover-upload:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: 0 15px 50px rgba(166, 128, 63, 0.15); }
        .cover-upload .upload-overlay { position: absolute; inset: 0; background: rgba(10, 47, 47, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; opacity: 0; transition: all 0.3s ease; z-index: 3; color: #fff; font-weight: 800; gap: 0.75rem; border-radius: 22px; }
        .cover-upload:hover .upload-overlay { opacity: 1; }
        .cover-empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.8rem; position: relative; z-index: 2; color: rgba(255,255,255,0.7); transition: all 0.3s; }
        .cover-upload:hover .cover-empty-state { opacity: 0; transform: scale(0.95); }
        .cover-empty-state i { width: 48px; height: 48px; color: var(--gold); filter: drop-shadow(0 4px 12px rgba(166, 128, 63, 0.3)); }
        .cover-empty-state span { font-weight: 700; font-size: 1.05rem; letter-spacing: 0.5px; }
        .cover-controls { margin-top: 0.85rem; display: flex; justify-content: flex-end; }
        .cover-color-box { display: flex; flex-direction: column; align-items: flex-end; gap: 0.55rem; padding: 0.75rem 0.9rem; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 5px 16px rgba(15, 23, 42, 0.06); width: 100%; max-width: 340px; }
        .cover-color-head { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; }
        .cover-color-head label { margin: 0; font-size: 0.78rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; }
        .cover-color-current { display: flex; align-items: center; gap: 0.6rem; }
        .cover-color-swatch { width: 28px; height: 28px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.55); }
        .cover-color-advanced-btn { border: 1px solid #d6dee8; background: #f8fafc; color: #334155; padding: 0.42rem 0.72rem; border-radius: 8px; font-size: 0.76rem; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .cover-color-advanced-btn:hover { border-color: #94a3b8; background: #f1f5f9; }
        .cover-color-picker-wrap { position: relative; display: inline-flex; }
        .cover-color-native-input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            border: 0;
            padding: 0;
        }
        .cover-preset-list { width: 100%; display: flex; align-items: center; justify-content: flex-end; gap: 0.45rem; flex-wrap: wrap; }
        .cover-preset-btn { width: 22px; height: 22px; border-radius: 50%; border: 1px solid rgba(15, 23, 42, 0.2); cursor: pointer; padding: 0; transition: 0.2s; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.4); }
        .cover-preset-btn:hover { transform: translateY(-1px) scale(1.06); }
        .cover-preset-btn.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(166,128,63,0.9); border-color: rgba(10,47,47,0.45); }
        
        .avatar-upload-container { position: absolute; bottom: -58px; left: 50%; transform: translateX(-50%); z-index: 20; }
        .avatar-upload { width: 140px; height: 140px; border-radius: 50%; background: transparent; padding: 0; position: relative; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .avatar-inner { width: 100%; height: 100%; border-radius: 50%; overflow: hidden; position: relative; background: transparent; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .avatar-upload:hover { transform: translateY(-5px); }
        .avatar-upload:hover .avatar-inner { border-color: var(--gold); }
        .avatar-inner img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; z-index: 2; }
        .avatar-upload .upload-overlay { position: absolute; inset: 0; background: rgba(10, 47, 47, 0.5); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; border-radius: 50%; color: #fff; z-index: 3; }
        .avatar-upload:hover .upload-overlay { opacity: 1; }
        .avatar-empty-state { position: relative; z-index: 1; display:flex; align-items:center; justify-content:center; width: 100%; height: 100%; border-radius: 50%; background: var(--avatar-base, #0A2F2F); color: var(--avatar-text, #FFFFFF); font-weight: 900; font-size: 2.2rem; letter-spacing: -0.8px; line-height: 1; transition: 0.3s; }
        .avatar-upload:hover .avatar-empty-state { transform: scale(0.95); opacity: 0.92; }
        .avatar-initial-settings { margin-top: 1.4rem; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.95rem 1rem; background: #f8fafc; }
        .avatar-initial-settings-head { display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; margin-bottom: 0.65rem; }
        .avatar-initial-settings-head strong { color: var(--navy-blue); font-size: 0.92rem; }
        .avatar-initial-settings-head span { color: #64748b; font-size: 0.76rem; font-weight: 700; }
        .avatar-initial-settings-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .avatar-initial-field { display: flex; align-items: center; gap: 0.6rem; }
        .avatar-initial-field input { width: 88px; text-align: center; font-weight: 900; letter-spacing: 0.5px; }
        .avatar-color-current { display: flex; align-items: center; gap: 0.5rem; }
        .avatar-color-swatch { width: 26px; height: 26px; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.6); }
        .avatar-preset-list { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; }
        .avatar-preset-btn { width: 20px; height: 20px; border-radius: 50%; border: 1px solid rgba(15, 23, 42, 0.2); cursor: pointer; padding: 0; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.45); transition: 0.2s; }
        .avatar-preset-btn:hover { transform: translateY(-1px) scale(1.06); }
        .avatar-preset-btn.is-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(166,128,63,0.9); border-color: rgba(10,47,47,0.45); }
        
        .form-section { padding: 2rem; background: #fff; border-radius: 20px; border: 1px solid #eef2f6; margin-bottom: 2rem; position: relative; z-index: 1; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .form-section::before { content: ''; position: absolute; inset: 0; border-radius: 20px; padding: 1px; background: linear-gradient(135deg, var(--gold), transparent 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.5s ease; z-index: -1; pointer-events: none; }
        .form-section:hover { border-color: transparent; box-shadow: 0 15px 40px rgba(166,128,63,0.06); transform: translateY(-2px); }
        .form-section:hover::before { opacity: 1; }
        .section-title { font-size: 1.15rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .section-title i { color: var(--gold); width: 20px; }
        
        .grid-forms { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem 2rem; }
        .form-field { margin-bottom: 1.25rem; }
        .form-field label { display: block; font-weight: 700; font-size: 0.82rem; color: #64748b; margin-bottom: 0.5rem; }
        .premium-input { width: 100%; padding: 0.9rem 1.1rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; color: var(--navy-blue); font-weight: 500; background: #fcfdfe; transition: all 0.3s ease; }
        .premium-input:focus { border-color: var(--gold); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(166,128,63,0.1); transform: translateY(-1px); }
        
        .social-link-item { 
            display: flex;
            align-items: center;
            gap: 1.25rem; 
            background: #fcfdfe; 
            padding: 1.25rem; 
            border-radius: 16px; 
            margin-bottom: 1rem; 
            border: 1px solid #eef2f6; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 1;
        }
        .social-link-item::before { content: ''; position: absolute; inset: 0; border-radius: 16px; padding: 1px; background: linear-gradient(135deg, rgba(166,128,63,0.8), transparent 80%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.4s ease; z-index: -1; pointer-events: none; }
        .social-link-item:hover { border-color: transparent; background: #fff; transform: translateX(6px); box-shadow: 0 8px 25px rgba(166,128,63,0.08); }
        .social-link-item:hover::before { opacity: 1; }
        .social-icon-wrapper {
            width: 48px;
            height: 48px;
            background: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy-blue);
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
            transition: 0.3s;
        }
        .social-brand-logo { width: 24px; height: 24px; object-fit: contain; display: block; }
        .social-link-item:hover .social-icon-wrapper { border-color: var(--gold); color: var(--gold); }
        .social-link-item select {
            width: 140px;
            border: 1px solid #e2e8f0;
            background: #fff;
            font-weight: 700;
            color: var(--navy-blue);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .social-link-item select:focus { outline: none; border-color: var(--gold); }
        .social-link-content { flex: 1; }
        .platform-logo-row { margin-top: 0.75rem; display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
        .platform-logo-row input[type="file"] { max-width: 280px; }
        .platform-logo-preview { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; object-fit: contain; padding: 0.25rem; }
        .platform-logo-hint { font-size: 0.72rem; color: #64748b; font-weight: 700; }
        .file-selection-meta { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; color: #475569; font-size: 0.82rem; }
        .file-selection-meta.is-empty { color: #94a3b8; }
        .file-selection-name { font-weight: 700; word-break: break-word; }
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
        .file-selection-link:hover { border-color: #cbd5e1; background: #f8fafc; transform: translateY(-1px); }
        
        .btn-remove { background: #fee2e2; color: #ef4444; border: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-remove:hover { background: #ef4444; color: #fff; transform: rotate(9deg) scale(1.1); }
        .add-link-cta {
            width: 100%;
            border: 1px solid rgba(166,128,63,0.35);
            background: linear-gradient(135deg, rgba(166,128,63,0.08), rgba(166,128,63,0.18));
            color: var(--navy-blue);
            border-radius: 14px;
            margin-top: 1.25rem;
            padding: 1rem 1.25rem;
            font-size: 0.98rem;
            font-weight: 800;
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .add-link-cta i { width: 18px; color: var(--gold); }
        .add-link-cta:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(166,128,63,0.12);
            background: linear-gradient(135deg, rgba(166,128,63,0.12), rgba(166,128,63,0.24));
        }
        
        .color-picker-box { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0; position: relative; z-index: 1; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .color-picker-box::before { content: ''; position: absolute; inset: 0; border-radius: 14px; padding: 1px; background: linear-gradient(135deg, var(--gold), transparent 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.4s ease; z-index: -1; pointer-events: none; }
        .color-picker-box:hover { border-color: transparent; background: #fff; box-shadow: 0 10px 30px rgba(166,128,63,0.08); transform: translateY(-2px); }
        .color-picker-box:hover::before { opacity: 1; }
        .palette-builder { margin-top: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; }
        .palette-builder-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.8rem; }
        .palette-builder-head p { margin: 0; font-size: 0.88rem; color: #64748b; font-weight: 600; }
        .palette-input-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .palette-color-list { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.9rem; }
        .palette-color-chip { display: inline-flex; align-items: center; gap: 0.45rem; background: #fff; border: 1px solid #dbe4ef; border-radius: 999px; padding: 0.28rem 0.55rem 0.28rem 0.28rem; font-size: 0.76rem; font-weight: 700; color: #334155; }
        .palette-color-chip-dot { width: 22px; height: 22px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.15); }
        .palette-color-remove { border: none; background: transparent; color: #ef4444; font-weight: 800; cursor: pointer; line-height: 1; padding: 0 0.15rem; }
        .btn-palette-add {
            border: 1px solid transparent;
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            border-radius: 10px;
            padding: 0.55rem 0.9rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(6, 36, 36, 0.2);
            transition: 0.25s ease;
        }
        .btn-palette-add:hover {
            background:
                linear-gradient(135deg, #0a3c3c, #125757) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(166, 128, 63, 0.2);
        }
        .qr-style-grid { margin-top: 1rem; display: grid; grid-template-columns: 1.1fr 1fr; gap: 1rem; }
        .qr-style-controls, .qr-style-preview-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; }
        .qr-style-controls .control-group { margin-bottom: 0.85rem; }
        .qr-style-controls .control-group:last-child { margin-bottom: 0; }
        .qr-style-controls label { display: block; font-weight: 700; color: #475569; margin-bottom: 0.4rem; font-size: 0.82rem; }
        .qr-style-controls .control-row { display: flex; align-items: center; gap: 0.6rem; }
        .qr-style-controls select { width: 100%; }
        .qr-preview-frame { width: 220px; height: 220px; margin: 0 auto; display: flex; align-items: center; justify-content: center; transition: 0.2s ease; background: #fff; }
        .qr-preview-frame.frame-classic { border-radius: 14px; border: 1px solid #dbe4ef; box-shadow: 0 8px 24px rgba(2, 28, 43, 0.06); }
        .qr-preview-frame.frame-soft { border-radius: 24px; background: linear-gradient(145deg, #ffffff, #f1f5f9); border: 1px solid #dbe4ef; box-shadow: 0 12px 30px rgba(2, 28, 43, 0.08); }
        .qr-preview-frame.frame-badge { border-radius: 999px; width: 240px; height: 240px; border: 10px solid #fff; box-shadow: 0 12px 30px rgba(2, 28, 43, 0.12); }
        .qr-preview-frame.frame-none { border: none; box-shadow: none; background: transparent; }
        .qr-preview-canvas { width: 190px; height: 190px; }
        .qr-preview-actions { margin-top: 0.9rem; text-align: center; }
        .btn-qr-download { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 10px; padding: 0.52rem 0.9rem; font-weight: 700; cursor: pointer; }
        .btn-qr-download:hover { border-color: var(--navy-blue); color: var(--navy-blue); }

        .ready-alert-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 2.5rem; display: flex; align-items: center; justify-content: space-between; gap: 3rem; margin-bottom: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02); position: relative; z-index: 1; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .ready-alert-box::before { content: ''; position: absolute; inset: 0; border-radius: 24px; padding: 1px; background: linear-gradient(135deg, var(--gold), rgba(166,128,63,0.1) 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.5s ease; z-index: -1; pointer-events: none; }
        .ready-alert-box:hover { border-color: transparent; box-shadow: 0 15px 50px rgba(166, 128, 63, 0.1); transform: translateY(-3px) scale(1.005); }
        .ready-alert-box:hover::before { opacity: 1; }

        .btn-save-fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            border: 1px solid transparent;
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.28s ease;
            box-shadow: 0 14px 28px rgba(6, 36, 36, 0.25);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .btn-save-fab:hover {
            background:
                linear-gradient(135deg, #0a3c3c, #125757) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            transform: translateY(-5px);
            box-shadow: 0 20px 34px rgba(166, 128, 63, 0.26);
        }

        @media (max-width: 1024px) {
            .grid-forms { grid-template-columns: 1fr; }
            .social-link-item { flex-direction: column; align-items: flex-start; }
            .social-link-item select { width: 100%; }
            .platform-logo-row input[type="file"] { width: 100%; max-width: 100%; }
            .ready-alert-box { flex-direction: column; text-align: center; padding: 1.5rem; gap: 1.5rem; }
            .ready-alert-box > div { width: 100%; }
            .ready-alert-box .qr-wrap { margin: 0 auto; }
            .qr-placeholder { transform: rotate(0); }
            .qr-style-grid { grid-template-columns: 1fr; }
            .qr-style-controls, .qr-style-preview-card { width: 100%; }
            .qr-preview-frame { width: 100%; max-width: 100%; height: auto; min-height: 240px; margin: 0 auto; }
            .qr-preview-frame.frame-badge { width: 220px; height: 220px; margin: 0 auto; }
            .qr-style-info-row { grid-template-columns: 1fr !important; }
            .package-summary-card { flex-direction: column; align-items: stretch !important; }
            .package-status-badge { min-width: 0 !important; width: 100%; text-align: center; margin-top: 1rem; }
        }
        @media (max-width: 480px) {
            .ready-alert-box { padding: 1.25rem 1rem; }
            .ready-alert-box h2 { font-size: 1.5rem !important; }
            .btn-save-fab { left: 1rem; right: 1rem; bottom: 1rem; justify-content: center; }
            .avatar-upload { width: 120px; height: 120px; }
            .profile-hero { margin-bottom: 4.5rem; }
        }
        @media (max-width: 360px) {
            .ready-alert-box h2 { font-size: 1.3rem !important; }
            .cover-upload { height: 180px; }
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
                <?php if ($resolved_package_for_access !== 'panel'): ?>
                    <li><a href="new-order.php"><i data-lucide="plus-circle"></i> Yeni Kartvizit Siparişi</a></li>
                <?php endif; ?>
                <li class="active"><a href="profile.php"><i data-lucide="user-cog"></i> Dijital Profilim</a></li>
                <?php if ($resolved_package_for_access !== 'panel'): ?>
                    <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <?php endif; ?>
                <li><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?></div>
                <div class="details">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Müşteri'); ?></span>
                    <span class="role">Premium Üye</span>
                </div>
            </div>
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Profilini Düzenle</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Dijital dünyadaki vitrininizi özelleştir.</p>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($profile_success_message !== ''): ?>
                <div style="background: #ecfdf5; color: #065f46; padding: 1rem 1.25rem; border-radius: 16px; border: 1px solid #86efac; margin-bottom: 1rem; font-weight: 700;">
                    <i data-lucide="check-circle" style="width: 18px; vertical-align: middle; margin-right: 0.4rem;"></i>
                    <?php echo htmlspecialchars($profile_success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($profile_error_message !== ''): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 1rem 1.25rem; border-radius: 16px; border: 1px solid #fecaca; margin-bottom: 1rem; font-weight: 700;">
                    <i data-lucide="alert-circle" style="width: 18px; vertical-align: middle; margin-right: 0.4rem;"></i>
                    <?php echo htmlspecialchars($profile_error_message); ?>
                </div>
            <?php endif; ?>

            <div style="display:grid; gap:1rem; margin-bottom:1.25rem;">
                <?php if ($is_preview_mode): ?>
                    <div style="background:linear-gradient(135deg, #eff6ff, #ffffff); border:1px solid #bfdbfe; color:#1d4ed8; border-radius:24px; padding:1rem 1.15rem; font-weight:700; line-height:1.7;">
                        <i data-lucide="sparkles" style="width:18px; vertical-align:middle; margin-right:0.4rem;"></i>
                        İnceleme modundasınız. Bu alanları kullanabilir ve sonucu görebilirsiniz. Kaydetme anında sistem sizi satın alma hazırlığı ekranına yönlendirecek.
                    </div>
                <?php endif; ?>
                <div style="background:linear-gradient(135deg, rgba(255,255,255,0.98), rgba(250,247,240,0.96)); border:1px solid #e7e5e4; border-radius:24px; padding:1.2rem 1.3rem; box-shadow:0 14px 34px rgba(15,23,42,0.04);">
                    <div class="package-summary-card" style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:0.8rem; color:#64748b; font-weight:800; text-transform:uppercase; letter-spacing:0.4px;"><?php echo htmlspecialchars($package_summary_label); ?></div>
                            <div style="font-size:1.25rem; font-weight:900; color:var(--navy-blue); margin-top:0.2rem;"><?php echo htmlspecialchars((string)$current_package_definition['label']); ?></div>
                            <p style="margin:0.55rem 0 0; color:#64748b; line-height:1.6;"><?php echo htmlspecialchars((string)$current_package_definition['description']); ?></p>
                        </div>
                        <div class="package-status-badge" style="padding:0.75rem 0.9rem; border-radius:18px; background:<?php echo htmlspecialchars($package_status_bg); ?>; color:<?php echo htmlspecialchars($package_status_color); ?>; font-weight:800; min-width:220px;">
                            <?php echo htmlspecialchars($package_status_text); ?>
                        </div>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:0.55rem; margin-top:1rem;">
                        <?php foreach (($current_package_definition['included_features'] ?? []) as $feature): ?>
                            <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 0.8rem; border-radius:999px; background:#f8fafc; border:1px solid #e2e8f0; color:#0f172a; font-weight:700; font-size:0.82rem;">
                                <i data-lucide="check" style="width:14px;"></i><?php echo htmlspecialchars((string)$feature); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach (($current_package_definition['excluded_features'] ?? []) as $feature): ?>
                            <span style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 0.8rem; border-radius:999px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-weight:700; font-size:0.82rem;">
                                <i data-lucide="minus" style="width:14px;"></i><?php echo htmlspecialchars((string)$feature); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
             
            <?php if ($public_profile_url !== ''): ?>
                <div class="ready-alert-box">
                    <div style="flex: 1;">
                        <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 0.5rem; letter-spacing: -0.5px;">Dijital Kartınız Hazır!</h2>
                        <p style="color: #64748b; font-weight: 500; font-size: 0.95rem; margin-bottom: 1.5rem;">Paylaşım seçeneklerini kullanarak profilinizi yaygınlaştırın.</p>
                        <div style="display: flex; background: #f8fafc; padding: 0.4rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                            <span style="flex: 1; padding: 0.6rem 1rem; font-size: 0.85rem; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600;" id="shareUrl"><?php echo htmlspecialchars($public_profile_url); ?></span>
                            <button type="button" style="background: var(--navy-blue); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: 0.2s;" onclick="copyToClipboard()">Kopyala</button>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="https://wa.me/?text=<?php echo urlencode('Dijital kartvizitimi inceleyin: ' . $public_profile_url); ?>" target="_blank" style="background:#f1f5f9; color:#1e293b; padding:0.6rem 1.2rem; border-radius:12px; text-decoration:none; font-weight:700; display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; transition:0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                <i data-lucide="message-circle" style="width:18px;"></i> WhatsApp
                            </a>
                            <a href="mailto:?subject=Dijital Kartvizitim&body=<?php echo urlencode($public_profile_url); ?>" style="background:#f1f5f9; color:#1e293b; padding:0.6rem 1.2rem; border-radius:12px; text-decoration:none; font-weight:700; display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; transition:0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                <i data-lucide="mail" style="width:18px;"></i> E-posta
                            </a>
                        </div>
                    </div>
                    <div class="qr-wrap" style="background: #fff; padding: 1rem; border-radius: 16px; border: 1px solid #eef2f6; text-align: center;">
                        <div id="shareQrInlineCanvas" data-fallback-src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($public_profile_url); ?>" aria-label="Paylaşım QR kodu" style="width: 140px; height: 140px; display: block; margin: 0 auto; border-radius: 8px; overflow: hidden;"></div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                            <button type="button" id="downloadShareQrBtn" onclick="downloadShareQrCard()" style="flex: 1; color:#1e293b; font-weight:800; text-decoration:none; font-size:0.75rem; border:1px solid #dbe4ef; background:#f8fafc; border-radius:10px; padding:0.55rem 0.5rem; cursor:pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <i data-lucide="download" style="width:14px;"></i> Tasarımlı
                            </button>
                            <button type="button" id="downloadPlainShareQrBtn" onclick="downloadPlainQr('share')" style="flex: 1; color:#1e293b; font-weight:800; text-decoration:none; font-size:0.75rem; border:1px solid #dbe4ef; background:#f8fafc; border-radius:10px; padding:0.55rem 0.5rem; cursor:pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <i data-lucide="qr-code" style="width:14px;"></i> Sadece QR
                            </button>
                        </div>
                        <div style="margin-top:0.45rem; font-size:0.72rem; color:#94a3b8; font-weight:600;">Profil paylaşımı için optimize edildi</div>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../processes/profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm">
                <?php echo csrf_input(); ?>

                <?php if ($is_digital_profile_active_for_package): ?>
                <div class="profile-hero" style="display:block;">
                    <div class="avatar-upload-container" style="margin:0 auto;">
                        <div class="avatar-upload" onclick="document.getElementById('photo').click()">
                            <div class="avatar-inner" id="avatarInnerBox" style="--avatar-base: <?php echo htmlspecialchars($resolved_avatar_color); ?>; --avatar-text: <?php echo htmlspecialchars($resolved_avatar_text_color); ?>;">
                                <?php if(!empty($profile['photo_path'])): ?>
                                    <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" id="avatarPreview">
                                <?php else: ?>
                                    <div class="avatar-empty-state" id="avatarEmptyState">
                                        <span id="avatarInitialPreviewText"><?php echo htmlspecialchars($resolved_avatar_initial); ?></span>
                                    </div>
                                    <img src="" id="avatarPreview" style="display:none;">
                                <?php endif; ?>
                                <div class="upload-overlay"><i data-lucide="camera" style="width:28px; height:28px;"></i></div>
                            </div>
                            <input type="file" id="photo" name="photo" hidden onchange="previewFile(this, 'avatarPreview')" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                    </div>
                    
                    <div style="margin-top:2.5rem; max-width:900px; margin-left:auto; margin-right:auto; padding-top: 3.5rem;">
                        <h3 class="section-title" style="justify-content:center; margin-bottom:1.5rem; font-size: 1.25rem;">
                            <i data-lucide="layout-template"></i> DİJİTAL KARTVİZİT TEMASI
                        </h3>
                        
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:1.25rem;">
                            <!-- Ana Kağıt Rengi -->
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:1.25rem; box-shadow:0 10px 25px rgba(0,0,0,0.02); transition:0.3s; position:relative;" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='#e2e8f0'">
                                <div style="display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem;">
                                    <div style="width:36px; height:36px; border-radius:10px; background:rgba(166,128,63,0.1); display:flex; align-items:center; justify-content:center; color:var(--gold);">
                                        <i data-lucide="palette" style="width:20px;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:0.85rem; font-weight:800; color:var(--navy-blue);">Ana Kağıt Rengi</div>
                                        <div style="font-size:0.7rem; color:#94a3b8; font-weight:600;">Kartın ana zemin rengi</div>
                                    </div>
                                </div>
                                
                                <div class="cp-studio-box">
                                    <div class="cp-mini-picker-wrap">
                                        <input type="color" id="cover_color" name="cover_color" value="<?php echo htmlspecialchars($resolved_cover_color); ?>">
                                    </div>
                                    <div class="cp-hex-box">
                                        <span class="cp-hint">Kod</span>
                                        <input type="text" id="cover_color_hex" value="<?php echo htmlspecialchars($resolved_cover_color); ?>" class="premium-input cp-hex-input">
                                    </div>
                                </div>
                            </div>

                            <!-- Yazı ve Avatar Rengi (Ortak) -->
                            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:1.25rem; box-shadow:0 10px 25px rgba(0,0,0,0.02); transition:0.3s;" onmouseover="this.style.borderColor='var(--gold)'" onmouseout="this.style.borderColor='#e2e8f0'">
                                <div style="display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem;">
                                    <div style="width:36px; height:36px; border-radius:10px; background:rgba(166,128,63,0.1); display:flex; align-items:center; justify-content:center; color:var(--gold);">
                                        <i data-lucide="type" style="width:20px;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:0.85rem; font-weight:800; color:var(--navy-blue);">Yazı ve Avatar Teması</div>
                                        <div style="font-size:0.7rem; color:#94a3b8; font-weight:600;">Tüm metin ve ikon renkleri</div>
                                    </div>
                                </div>

                                <div class="cp-studio-box">
                                    <div class="cp-mini-picker-wrap">
                                        <input type="color" id="text_color_picker" name="text_color" value="<?php echo htmlspecialchars($resolved_text_color); ?>">
                                    </div>
                                    <div class="cp-hex-box">
                                        <span class="cp-hint">Kod</span>
                                        <input type="text" id="text_color_hex" value="<?php echo htmlspecialchars($resolved_text_color); ?>" class="premium-input cp-hex-input">
                                    </div>
                                </div>

                                <!-- Gizli input: Avatar zemin rengi artık Yazı Rengi ile aynı olacak -->
                                <input type="hidden" id="avatar_color" name="avatar_color" value="<?php echo htmlspecialchars($resolved_avatar_color); ?>">
                                <input type="hidden" id="avatar_color_hex" value="<?php echo htmlspecialchars($resolved_avatar_color); ?>">
                            </div>
                        </div>

                        <div class="avatar-initial-settings" style="max-width:100%; margin:1.5rem auto 0; background:rgba(166,128,63,0.03); border-color:rgba(166,128,63,0.1);">
                            <div class="avatar-initial-settings-head">
                                <strong style="color:var(--gold);">Profesyonel İpucu</strong>
                                <span style="font-size:0.8rem; font-weight:600;">Seçtiğiniz renklerin birbirine zıt (kontrast) olması okunabilirliği artırır.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif ($show_profile_qr_customization): ?>
                    <div style="background:#fffaf0; border:1px solid #fcd34d; color:#92400e; border-radius:24px; padding:1.1rem 1.2rem; margin-bottom:1.2rem; font-weight:700; line-height:1.7;">
                        Bu hesapta dijital profil tasarım modülü kapalı. Temel kimlik bilgilerinizi güncelleyebilirsiniz; kapak, avatar, QR ve sosyal link alanları bu pakette düzenlenemez.
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2.5rem;">
                        <h3 class="section-title" style="margin-bottom:0;"><i data-lucide="user"></i> Temel Bilgiler</h3>
                        <?php if ($public_profile_url !== ''): ?>
                            <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" style="color:var(--gold); font-weight:900; text-decoration:none; font-size:1rem; display:flex; align-items:center; gap:0.6rem; background:rgba(166,128,63,0.08); padding:0.6rem 1.25rem; border-radius:14px; transition:0.3s;" onmouseover="this.style.background='rgba(166,128,63,0.15)'">
                                Canlı Profiline Git <i data-lucide="external-link" style="width:18px;"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="grid-forms">
                        <div class="form-field">
                            <label>Ad Soyad</label>
                            <input type="text" name="full_name" class="premium-input" value="<?php echo htmlspecialchars($profile['full_name']); ?>" placeholder="Adınız Soyadınız">
                        </div>
                        <div class="form-field">
                            <label>Mesleki Unvan</label>
                            <input type="text" name="title" class="premium-input" value="<?php echo htmlspecialchars($profile['title']); ?>" placeholder="Yazılım Geliştirici, CEO, vb.">
                        </div>
                        <div class="form-field">
                            <label>?irket Adı</label>
                            <input type="text" name="company" class="premium-input" value="<?php echo htmlspecialchars($profile['company']); ?>" placeholder="Zerosoft Teknoloji">
                        </div>
                        <div class="form-field">
                            <label>İş Telefonu</label>
                            <input type="tel" name="phone_work" class="premium-input" value="<?php echo htmlspecialchars($profile['phone_work'] ?: ''); ?>" placeholder="05xx xxx xx xx">
                        </div>
                    </div>
                    <div class="form-field" style="margin-bottom:0;">
                        <label>Biyografi / Hakkında</label>
                        <textarea name="bio" class="premium-input" rows="4" style="resize:none;" placeholder="Kendinizden kısaca bahsedin..."><?php echo htmlspecialchars($profile['bio']); ?></textarea>
                    </div>
                </div>

                <?php if ($is_digital_profile_active_for_package): ?>
                <input type="hidden" id="brand_color" name="brand_color" value="<?php echo htmlspecialchars($resolved_brand_color); ?>">
                <input type="hidden" name="brand_palette" id="brand_palette" value='<?php echo htmlspecialchars(json_encode($resolved_brand_palette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'>

                <?php if ($lock_profile_qr_to_print_design): ?>
                    <!-- Baskılı paketlerde QR ayarları gizli, mevcut değerler korunur -->
                    <input type="hidden" name="qr_color" id="qr_color" value="<?php echo htmlspecialchars($default_qr_style['qr_color']); ?>">
                    <input type="hidden" id="qr_color_hex" value="<?php echo htmlspecialchars($default_qr_style['qr_color']); ?>">
                    <input type="hidden" name="qr_bg_color" id="qr_bg_color" value="<?php echo htmlspecialchars($default_qr_style['qr_bg_color']); ?>">
                    <input type="hidden" id="qr_bg_color_hex" value="<?php echo htmlspecialchars($default_qr_style['qr_bg_color']); ?>">
                    <input type="hidden" name="qr_dot_style" id="qr_dot_style" value="<?php echo htmlspecialchars($default_qr_style['qr_dot_style']); ?>">
                    <input type="hidden" name="qr_corner_style" id="qr_corner_style" value="<?php echo htmlspecialchars($default_qr_style['qr_corner_style']); ?>">
                    <input type="hidden" name="qr_frame_style" id="qr_frame_style" value="<?php echo htmlspecialchars($default_qr_style['qr_frame_style']); ?>">
                <?php elseif ($show_profile_qr_customization): ?>
                    <div class="form-section">
                        <h3 class="section-title"><i data-lucide="qr-code"></i> QR Kod Özelleştirme</h3>
                        
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1rem 1.25rem; margin-bottom:1.5rem; font-weight:600; line-height:1.6; color: #475569;">
                            <i data-lucide="info" style="width:18px; vertical-align:middle; margin-right:4px; color: var(--gold);"></i> Burada belirleyeceğiniz QR stili dijital kartvizitinizde kullanılacaktır.
                        </div>

                        <div class="grid-forms">
                            <div class="form-field">
                                <label for="qr_color">QR Rengi</label>
                                <div style="display:flex; gap:0.75rem; align-items:center;">
                                    <input type="color" id="qr_color" name="qr_color" value="<?php echo htmlspecialchars($default_qr_style['qr_color']); ?>" style="width:64px; height:52px; padding:0.25rem; border-radius:14px; border:1px solid #dbe4ef;">
                                    <input type="text" id="qr_color_hex" value="<?php echo htmlspecialchars($default_qr_style['qr_color']); ?>" class="premium-input" style="margin:0;">
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="qr_bg_color">QR Arka Planı</label>
                                <div style="display:flex; gap:0.75rem; align-items:center;">
                                    <input type="color" id="qr_bg_color" name="qr_bg_color" value="<?php echo htmlspecialchars($default_qr_style['qr_bg_color']); ?>" style="width:64px; height:52px; padding:0.25rem; border-radius:14px; border:1px solid #dbe4ef;">
                                    <input type="text" id="qr_bg_color_hex" value="<?php echo htmlspecialchars($default_qr_style['qr_bg_color']); ?>" class="premium-input" style="margin:0;">
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="qr_dot_style">Nokta Stili</label>
                                <select id="qr_dot_style" name="qr_dot_style" class="premium-input">
                                    <?php foreach (['square' => 'Kare', 'dots' => 'Yuvarlak', 'rounded' => 'Yumuşak Köşe', 'extra-rounded' => 'Oval', 'classy' => 'Klasik', 'classy-rounded' => 'Şık'] as $style_key => $style_label): ?>
                                        <option value="<?php echo htmlspecialchars($style_key); ?>" <?php echo $default_qr_style['qr_dot_style'] === $style_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($style_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="qr_corner_style">Köşe Stili</label>
                                <select id="qr_corner_style" name="qr_corner_style" class="premium-input">
                                    <?php foreach (['square' => 'Kare', 'dot' => 'Nokta', 'extra-rounded' => 'Yuvarlak'] as $style_key => $style_label): ?>
                                        <option value="<?php echo htmlspecialchars($style_key); ?>" <?php echo $default_qr_style['qr_corner_style'] === $style_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($style_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-field">
                                <label for="qr_frame_style">Çerçeve Stili</label>
                                <select id="qr_frame_style" name="qr_frame_style" class="premium-input">
                                    <?php foreach (['classic' => 'Klasik', 'soft' => 'Yumuşak', 'badge' => 'Rozet', 'none' => 'Çerçevesiz'] as $style_key => $style_label): ?>
                                        <option value="<?php echo htmlspecialchars($style_key); ?>" <?php echo $default_qr_style['qr_frame_style'] === $style_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($style_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:1rem; display:grid; grid-template-columns:minmax(0, 1fr) 220px; gap:1rem; align-items:start;" class="qr-style-info-row">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:20px; padding:1.2rem; color:#64748b; line-height:1.7;">
                                <strong style="color: var(--navy-blue); display: block; margin-bottom: 0.5rem; font-size: 1rem;">Profesyonel Görünüm</strong>
                                Seçtiğiniz QR kod stili dijital profilinizde kullanılacaktır. Okutulabilirlik için yüksek kontrastlı renkler tercih etmenizi öneririz.
                            </div>
                            <div id="qrPreviewFrame" class="<?php echo htmlspecialchars('frame-' . $default_qr_style['qr_frame_style']); ?>" style="background:#fff; border:1px solid #e2e8f0; border-radius:22px; padding:1rem; text-align:center;">
                                <div id="qrPreviewCanvas" style="width:190px; height:190px; margin:0 auto;"></div>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.9rem;">
                                    <button type="button" id="downloadStyledQrBtn" style="flex: 1; border:1px solid #dbe4ef; background:#f8fafc; color:#0A2F2F; border-radius:12px; padding:0.7rem 0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer;">Tasarımlı İndir</button>
                                    <button type="button" id="downloadPlainStyledQrBtn" onclick="downloadPlainQr('styled')" style="flex: 1; border:1px solid #dbe4ef; background:#f8fafc; color:#0A2F2F; border-radius:12px; padding:0.7rem 0.5rem; font-size:0.75rem; font-weight:800; cursor:pointer;">Sadece QR</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <h3 class="section-title"><i data-lucide="share-2"></i> Sosyal Medya & Linkler</h3>
                    <div id="social-links-container">
                        <?php if(empty($links)): ?>
                            <div class="social-link-item">
                                <div class="social-icon-wrapper">
                                    <img src="<?php echo htmlspecialchars((string)qrk_get_social_platform_meta('instagram')['logo']); ?>" alt="Instagram logosu" class="social-brand-logo">
                                </div>
                                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                    <?php foreach ($social_platform_options as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-logo="<?php echo htmlspecialchars((string)$opt['logo']); ?>" <?php echo $opt['value'] === 'instagram' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opt['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="social-link-content">
                                    <input type="text" name="urls[]" class="premium-input" placeholder="Linkinizi veya kullanıcı adınızı yapıştırın...">
                                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;">
                                    <input type="hidden" name="existing_platform_logos[]" value="">
                                    <div class="platform-logo-row" style="display:none;">
                                        <input type="file" name="platform_logos[]" class="premium-input platform-logo-input" accept="image/jpeg,image/png,image/webp" style="padding:0.55rem 0.7rem;">
                                        <img src="" alt="Logo önizleme" class="platform-logo-preview" style="display:none;">
                                        <span class="platform-logo-hint">Diğer platform için logo (opsiyonel)</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
                            </div>
                        <?php else: ?>
                            <?php foreach($links as $link): ?>
                                <?php
                                    $platform_raw = strtolower(trim((string)($link['platform'] ?? '')));
                                    $is_custom_platform = !in_array($platform_raw, $social_platform_values, true);
                                    $selected_platform = $is_custom_platform ? '__custom__' : $platform_raw;
                                    $custom_platform_value = $is_custom_platform ? $platform_raw : '';
                                    $existing_custom_logo = trim((string)($link['logo_path'] ?? ''));
                                    $existing_custom_logo = preg_match('#^assets/uploads/social_logos/[A-Za-z0-9._-]+$#', $existing_custom_logo) ? $existing_custom_logo : '';
                                     
                                    $platform_info = array_filter($social_platform_options, fn($o) => $o['value'] === $selected_platform);
                                    $logo = !empty($platform_info) ? (string)reset($platform_info)['logo'] : (string)qrk_get_social_platform_meta('__custom__')['logo'];
                                ?>
                                <div class="social-link-item">
                                    <div class="social-icon-wrapper">
                                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="<?php echo htmlspecialchars($selected_platform); ?> logosu" class="social-brand-logo">
                                    </div>
                                    <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                        <?php foreach ($social_platform_options as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-logo="<?php echo htmlspecialchars((string)$opt['logo']); ?>" <?php echo $opt['value'] === $selected_platform ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="social-link-content">
                                        <input type="text" name="urls[]" class="premium-input" value="<?php echo htmlspecialchars($link['url']); ?>" placeholder="Linkinizi buraya yapıştırın...">
                                        <input type="text" name="platform_customs[]" class="premium-input" value="<?php echo htmlspecialchars($custom_platform_value); ?>" <?php echo $is_custom_platform ? '' : 'style="display:none; margin-top:0.75rem;"'; ?>>
                                        <input type="hidden" name="existing_platform_logos[]" value="<?php echo htmlspecialchars($existing_custom_logo); ?>">
                                        <div class="platform-logo-row" <?php echo $is_custom_platform ? '' : 'style="display:none;"'; ?>>
                                            <input type="file" name="platform_logos[]" class="premium-input platform-logo-input" accept="image/jpeg,image/png,image/webp" style="padding:0.55rem 0.7rem;">
                                            <?php if ($existing_custom_logo !== ''): ?>
                                                <img src="../<?php echo htmlspecialchars($existing_custom_logo); ?>" alt="Logo önizleme" class="platform-logo-preview">
                                            <?php else: ?>
                                                <img src="" alt="Logo önizleme" class="platform-logo-preview" style="display:none;">
                                            <?php endif; ?>
                                            <span class="platform-logo-hint">Diğer platform için logo (opsiyonel)</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addSocialLink()" class="add-link-cta">
                        <i data-lucide="plus-circle"></i> Yeni Sosyal Link Ekle
                    </button>
                </div>

                <?php endif; ?>

                <div style="height: 120px;"></div>
                
                <button type="submit" class="btn-save-fab">
                    Değişiklikleri Kaydet <i data-lucide="save"></i>
                </button>
            </form>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script src="https://unpkg.com/qr-code-styling@1.6.0/lib/qr-code-styling.js"></script>
    <script>
        lucide.createIcons();
        
        const SOCIAL_OPTIONS = <?php echo json_encode($social_platform_options); ?>;

        function previewFile(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    let preview = document.getElementById(previewId);
                    if (!preview) {
                        const parent = input.parentElement;
                        const newImg = document.createElement('img');
                        newImg.id = previewId;
                        parent.prepend(newImg);
                        preview = newImg;
                    }
                    preview.src = e.target.result;
                    preview.style.display = 'block';

                    if (previewId === 'coverPreview') {
                        const emptyState = document.getElementById('coverEmptyState');
                        if (emptyState) emptyState.style.display = 'none';
                    } else if (previewId === 'avatarPreview') {
                        const emptyState = document.getElementById('avatarEmptyState');
                        if (emptyState) emptyState.style.display = 'none';
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function ensureFileSelectionMeta(input) {
            if (!input) return null;
            const holder = input.closest('.platform-logo-row') || input.parentElement;
            if (!holder) return null;

            let meta = holder.querySelector('[data-file-selection-meta]');
            if (!meta) {
                meta = document.createElement('div');
                meta.className = 'file-selection-meta is-empty';
                meta.dataset.fileSelectionMeta = '1';
                meta.innerHTML = '<span class="file-selection-name">Henüz dosya seçilmedi</span><a class="file-selection-link" href="#" target="_blank" rel="noopener" hidden>Aç</a>';
                holder.appendChild(meta);
            }

            return meta;
        }

        function updateFileSelectionMeta(input, emptyText = 'Henüz dosya seçilmedi') {
            const meta = ensureFileSelectionMeta(input);
            if (!meta) return;

            const nameEl = meta.querySelector('.file-selection-name');
            const linkEl = meta.querySelector('.file-selection-link');
            const file = input && input.files && input.files[0] ? input.files[0] : null;

            if (input && input.dataset.objectUrl) {
                URL.revokeObjectURL(input.dataset.objectUrl);
                delete input.dataset.objectUrl;
            }

            if (!file) {
                meta.classList.add('is-empty');
                if (nameEl) nameEl.textContent = emptyText;
                if (linkEl) {
                    linkEl.hidden = true;
                    linkEl.removeAttribute('href');
                }
                return;
            }

            meta.classList.remove('is-empty');
            if (nameEl) {
                nameEl.textContent = `${file.name} (${Math.max(1, Math.round(file.size / 1024))} KB)`;
            }

            if (linkEl) {
                const objectUrl = URL.createObjectURL(file);
                input.dataset.objectUrl = objectUrl;
                linkEl.href = objectUrl;
                linkEl.hidden = false;
            }
        }

        function bindFileSelectionMeta(input, emptyText = 'Henüz dosya seçilmedi') {
            if (!input || input.dataset.fileMetaBound === '1') return;
            input.dataset.fileMetaBound = '1';
            updateFileSelectionMeta(input, emptyText);
            input.addEventListener('change', () => updateFileSelectionMeta(input, emptyText));
        }

        function syncColorPicker(input) {
            const normalized = normalizeHexColor(input.value);
            if (normalized) {
                const brandColorEl = document.getElementById('brand_color');
                const brandColorHexEl = document.getElementById('brand_color_hex');
                if (brandColorEl) brandColorEl.value = normalized;
                if (brandColorHexEl) brandColorHexEl.value = normalized;
            }
        }

        function normalizeHexColor(value) {
            const raw = String(value || '').trim().toUpperCase();
            return /^#[0-9A-F]{6}$/.test(raw) ? raw : '';
        }

        const brandColorEl = document.getElementById('brand_color');
        const brandColorHexEl = document.getElementById('brand_color_hex');
        const palettePickerEl = document.getElementById('palette_color_picker');
        const paletteHexEl = document.getElementById('palette_color_hex');
        const paletteListEl = document.getElementById('paletteColorList');
        const paletteHiddenEl = document.getElementById('brand_palette');
        const qrColorEl = document.getElementById('qr_color');
        const qrColorHexEl = document.getElementById('qr_color_hex');
        const qrBgColorEl = document.getElementById('qr_bg_color');
        const qrBgColorHexEl = document.getElementById('qr_bg_color_hex');
        const qrDotStyleEl = document.getElementById('qr_dot_style');
        const qrCornerStyleEl = document.getElementById('qr_corner_style');
        const qrFrameStyleEl = document.getElementById('qr_frame_style');
        const qrPreviewFrameEl = document.getElementById('qrPreviewFrame');
        const qrPreviewCanvasEl = document.getElementById('qrPreviewCanvas');
        const shareQrInlineCanvasEl = document.getElementById('shareQrInlineCanvas');
        const qrDownloadBtnEl = document.getElementById('downloadStyledQrBtn');
        const shareQrDownloadBtnEl = document.getElementById('downloadShareQrBtn');
        const coverUploadBoxEl = document.getElementById('coverUploadBox');
        const coverColorEl = document.getElementById('cover_color');
        const coverColorSwatchEl = document.getElementById('coverColorSwatch');
        const coverPresetButtons = Array.from(document.querySelectorAll('.cover-preset-btn'));
        const avatarInnerBoxEl = document.getElementById('avatarInnerBox');
        const avatarInitialInputEl = document.getElementById('avatar_initial');
        const avatarInitialPreviewTextEl = document.getElementById('avatarInitialPreviewText');
        const avatarColorEl = document.getElementById('avatar_color');
        const avatarColorSwatchEl = document.getElementById('avatarColorSwatch');
        const avatarPresetButtons = Array.from(document.querySelectorAll('.avatar-preset-btn'));
        const textColorPickerEl = document.getElementById('text_color_picker');
        const textColorHexEl = document.getElementById('text_color_hex');
        const coverColorHexEl = document.getElementById('cover_color_hex');

        const QR_PREVIEW_TARGET = <?php echo json_encode($public_profile_url !== '' ? $public_profile_url : project_base_url_for_customer_panel()); ?>;
        const SHARE_QR_TARGET = <?php echo json_encode($public_profile_url !== '' ? $public_profile_url : project_base_url_for_customer_panel()); ?>;
        const SHARE_QR_PROFILE_NAME = <?php echo json_encode(trim((string)($profile['full_name'] ?? ($_SESSION['user_name'] ?? 'Dijital Kartvizit')))); ?>;
        const SHARE_QR_FILE_STEM = <?php echo json_encode($profile_slug !== '' ? $profile_slug : ('qr-' . (int)$user_id)); ?>;
        const paletteState = { colors: [] };
        let qrStyling = null;
        let shareQrStyling = null;

        function syncPickerAndHex(pickerEl, hexEl, onValid) {
            if (!pickerEl || !hexEl) return;
            pickerEl.addEventListener('input', (event) => {
                const color = normalizeHexColor(event.target.value);
                if (!color) return;
                hexEl.value = color;
                if (typeof onValid === 'function') onValid(color);
            });
            hexEl.addEventListener('input', (event) => {
                const color = normalizeHexColor(event.target.value);
                if (!color) return;
                pickerEl.value = color;
                hexEl.value = color;
                if (typeof onValid === 'function') onValid(color);
            });
        }

        function darkenHexColor(value, factor) {
            const hex = normalizeHexColor(value);
            if (!hex) return '';
            const amount = Math.min(Math.max(Number(factor) || 0, 0), 1);
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const nextR = Math.max(0, Math.round(r * (1 - amount)));
            const nextG = Math.max(0, Math.round(g * (1 - amount)));
            const nextB = Math.max(0, Math.round(b * (1 - amount)));
            return `#${nextR.toString(16).padStart(2, '0')}${nextG.toString(16).padStart(2, '0')}${nextB.toString(16).padStart(2, '0')}`.toUpperCase();
        }

        function applyCoverColorPreview(color) {
            if (!coverUploadBoxEl) return;
            const normalized = normalizeHexColor(color);
            if (!normalized) return;
            coverUploadBoxEl.style.setProperty('--cover-base', normalized);
            coverUploadBoxEl.style.setProperty('--cover-dark', darkenHexColor(normalized, 0.34));
        }

        function setCoverColor(color) {
            const normalized = normalizeHexColor(color);
            if (!normalized) return;
            if (coverColorEl) coverColorEl.value = normalized;
            if (brandColorEl) brandColorEl.value = normalized;
            if (coverColorSwatchEl) coverColorSwatchEl.style.backgroundColor = normalized;
            applyCoverColorPreview(normalized);
            
            // Yeni mantık: Avatar içindeki harf rengi her zaman Ana Kağıt Rengi ile aynı olur
            if (avatarInnerBoxEl) {
                avatarInnerBoxEl.style.setProperty('--avatar-text', normalized);
            }
            if (avatarInitialPreviewTextEl) {
                avatarInitialPreviewTextEl.style.color = normalized;
            }

            coverPresetButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.color === normalized);
            });
        }

        function normalizeInitialValue(value) {
            let cleaned = String(value || '').trim();
            if (!cleaned) return '';
            try {
                cleaned = cleaned.replace(/\s+/gu, '').replace(/\p{C}+/gu, '');
            } catch (error) {
                cleaned = cleaned.replace(/\s+/g, '');
            }
            return Array.from(cleaned).slice(0, 2).join('');
        }

        function getContrastTextColor(hex) {
            const normalized = normalizeHexColor(hex);
            if (!normalized) return '#FFFFFF';
            const r = parseInt(normalized.slice(1, 3), 16);
            const g = parseInt(normalized.slice(3, 5), 16);
            const b = parseInt(normalized.slice(5, 7), 16);
            const luminance = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            return luminance >= 160 ? '#0F172A' : '#FFFFFF';
        }

        function setAvatarInitial(value) {
            const normalized = normalizeInitialValue(value);
            if (avatarInitialInputEl && avatarInitialInputEl.value !== normalized) {
                avatarInitialInputEl.value = normalized;
            }
            if (avatarInitialPreviewTextEl) avatarInitialPreviewTextEl.textContent = normalized || 'A';
        }

        function setAvatarColor(color) {
            const normalized = normalizeHexColor(color);
            if (!normalized) return;
            if (avatarColorEl) avatarColorEl.value = normalized;
            if (avatarColorHexEl) avatarColorHexEl.value = normalized;
            if (avatarInnerBoxEl) {
                avatarInnerBoxEl.style.setProperty('--avatar-base', normalized);
            }
        }

        function setTextColor(color) {
            const normalized = normalizeHexColor(color);
            if (!normalized) return;
            if (textColorPickerEl) textColorPickerEl.value = normalized;
            if (textColorHexEl) textColorHexEl.value = normalized;
            
            // Yeni mantık: Avatar zemin rengi her zaman Yazı Rengi ile aynı olur
            if (avatarInnerBoxEl) {
                avatarInnerBoxEl.style.setProperty('--avatar-base', normalized);
            }
            if (avatarColorEl) avatarColorEl.value = normalized;
            if (avatarColorHexEl) avatarColorHexEl.value = normalized;
        }

        function loadPaletteState() {
            if (!paletteHiddenEl) return;
            let parsed = [];
            try {
                const raw = JSON.parse(paletteHiddenEl.value || '[]');
                if (Array.isArray(raw)) {
                    parsed = raw
                        .map((color) => normalizeHexColor(color))
                        .filter((color) => color !== '');
                }
            } catch (error) {
                parsed = [];
            }

            const unique = [];
            parsed.forEach((color) => {
                if (!unique.includes(color)) unique.push(color);
            });

            const brandColor = normalizeHexColor(
                (brandColorEl && brandColorEl.value) || (coverColorEl && coverColorEl.value) || ''
            );
            if (brandColor && !unique.includes(brandColor)) {
                unique.unshift(brandColor);
            }

            paletteState.colors = unique.slice(0, 6);
            paletteHiddenEl.value = JSON.stringify(paletteState.colors);
        }

        function renderPaletteState() {
            if (!paletteListEl || !paletteHiddenEl) return;
            paletteListEl.innerHTML = '';

            paletteState.colors.forEach((color) => {
                const chip = document.createElement('span');
                chip.className = 'palette-color-chip';

                const dot = document.createElement('span');
                dot.className = 'palette-color-chip-dot';
                dot.style.backgroundColor = color;

                const label = document.createElement('span');
                label.textContent = color;

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'palette-color-remove';
                removeBtn.textContent = 'x';
                removeBtn.addEventListener('click', () => {
                    paletteState.colors = paletteState.colors.filter((item) => item !== color);
                    paletteHiddenEl.value = JSON.stringify(paletteState.colors);
                    renderPaletteState();
                });

                chip.appendChild(dot);
                chip.appendChild(label);
                chip.appendChild(removeBtn);
                paletteListEl.appendChild(chip);
            });

            paletteHiddenEl.value = JSON.stringify(paletteState.colors);
        }

        function addPaletteColor() {
            const color = normalizeHexColor((paletteHexEl && paletteHexEl.value) || (palettePickerEl && palettePickerEl.value) || '');
            if (!color) return;
            if (!paletteState.colors.includes(color)) {
                if (paletteState.colors.length >= 6) {
                    paletteState.colors.shift();
                }
                paletteState.colors.push(color);
                renderPaletteState();
            }
        }
        window.addPaletteColor = addPaletteColor;

        function applyQrFrameStyle() {
            if (!qrPreviewFrameEl || !qrFrameStyleEl) return;
            qrPreviewFrameEl.classList.remove('frame-classic', 'frame-soft', 'frame-badge', 'frame-none');
            qrPreviewFrameEl.classList.add(`frame-${qrFrameStyleEl.value || 'classic'}`);
        }

        function buildQrOptions({ width, height, data }) {
            const qrColor = normalizeHexColor(qrColorHexEl ? qrColorHexEl.value : '') || '#0A2F2F';
            const qrBgColor = normalizeHexColor(qrBgColorHexEl ? qrBgColorHexEl.value : '') || '#FFFFFF';
            const dotType = (qrDotStyleEl && qrDotStyleEl.value) || 'square';
            const cornerType = (qrCornerStyleEl && qrCornerStyleEl.value) || 'square';

            return {
                width,
                height,
                data,
                type: 'canvas',
                margin: 0,
                qrOptions: { errorCorrectionLevel: 'M' },
                dotsOptions: {
                    color: qrColor,
                    type: dotType
                },
                backgroundOptions: {
                    color: qrBgColor
                },
                cornersSquareOptions: {
                    color: qrColor,
                    type: cornerType
                },
                cornersDotOptions: {
                    color: qrColor,
                    type: cornerType === 'dot' ? 'dot' : 'square'
                }
            };
        }

        function renderShareInlineQr() {
            if (!shareQrInlineCanvasEl) return;
            if (typeof QRCodeStyling === 'undefined') {
                const fallbackSrc = shareQrInlineCanvasEl.dataset.fallbackSrc || '';
                if (fallbackSrc !== '') {
                    shareQrInlineCanvasEl.innerHTML = `<img src="${fallbackSrc}" alt="QR Code" style="width:140px;height:140px;display:block;border-radius:8px;">`;
                }
                return;
            }

            const nextOptions = buildQrOptions({
                width: 140,
                height: 140,
                data: SHARE_QR_TARGET
            });

            if (!shareQrStyling) {
                shareQrStyling = new QRCodeStyling(nextOptions);
                shareQrInlineCanvasEl.innerHTML = '';
                shareQrStyling.append(shareQrInlineCanvasEl);
                return;
            }

            shareQrStyling.update(nextOptions);
        }

        function renderQrPreview() {
            if (!qrPreviewCanvasEl) return;

            applyQrFrameStyle();
            if (typeof QRCodeStyling === 'undefined') {
                qrPreviewCanvasEl.innerHTML = '<p style="font-size:12px;color:#64748b;text-align:center;">QR preview yuklenemedi.</p>';
                renderShareInlineQr();
                return;
            }

            const nextOptions = buildQrOptions({
                width: 190,
                height: 190,
                data: QR_PREVIEW_TARGET
            });

            if (!qrStyling) {
                qrStyling = new QRCodeStyling(nextOptions);
                qrPreviewCanvasEl.innerHTML = '';
                qrStyling.append(qrPreviewCanvasEl);
                renderShareInlineQr();
                return;
            }

            qrStyling.update(nextOptions);
            renderShareInlineQr();
        }

        function sanitizeFileName(value) {
            return String(value || 'qr-kartvizit')
                .toLowerCase()
                .replace(/[^a-z0-9-_]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '')
                .slice(0, 64) || 'qr-kartvizit';
        }

        function drawRoundedRect(ctx, x, y, width, height, radius) {
            const r = Math.max(0, Math.min(radius, Math.min(width, height) / 2));
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.arcTo(x + width, y, x + width, y + height, r);
            ctx.arcTo(x + width, y + height, x, y + height, r);
            ctx.arcTo(x, y + height, x, y, r);
            ctx.arcTo(x, y, x + width, y, r);
            ctx.closePath();
        }

        function loadImageFromBlob(blob) {
            return new Promise((resolve, reject) => {
                const url = URL.createObjectURL(blob);
                const img = new Image();
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    resolve(img);
                };
                img.onerror = () => {
                    URL.revokeObjectURL(url);
                    reject(new Error('image_load_failed'));
                };
                img.src = url;
            });
        }

        async function composeQrCardAndDownload({ qrBlob, title, subtitle, urlText, fileStem }) {
            const qrImage = await loadImageFromBlob(qrBlob);

            const canvas = document.createElement('canvas');
            canvas.width = 520;
            canvas.height = 660;
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                throw new Error('canvas_context_unavailable');
            }

            ctx.fillStyle = '#F3F6FB';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const cardX = 28;
            const cardY = 24;
            const cardW = canvas.width - (cardX * 2);
            const cardH = canvas.height - (cardY * 2);

            drawRoundedRect(ctx, cardX, cardY, cardW, cardH, 26);
            ctx.fillStyle = '#FFFFFF';
            ctx.fill();

            const borderGradient = ctx.createLinearGradient(cardX, cardY, cardX + cardW, cardY + cardH);
            borderGradient.addColorStop(0, '#F3E1AE');
            borderGradient.addColorStop(0.52, '#CFA75E');
            borderGradient.addColorStop(1, '#A6803F');
            ctx.lineWidth = 3;
            ctx.strokeStyle = borderGradient;
            ctx.stroke();

            ctx.fillStyle = '#0A2F2F';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            ctx.font = '800 31px Inter, sans-serif';
            ctx.fillText(title, canvas.width / 2, 66);

            ctx.fillStyle = '#64748B';
            ctx.font = '600 17px Inter, sans-serif';
            ctx.fillText(subtitle, canvas.width / 2, 110);

            const qrSize = 270;
            const qrX = Math.round((canvas.width - qrSize) / 2);
            const qrY = 150;
            drawRoundedRect(ctx, qrX - 14, qrY - 14, qrSize + 28, qrSize + 28, 22);
            ctx.fillStyle = '#FFFFFF';
            ctx.fill();
            ctx.strokeStyle = '#E2E8F0';
            ctx.lineWidth = 2;
            ctx.stroke();
            ctx.drawImage(qrImage, qrX, qrY, qrSize, qrSize);

            ctx.fillStyle = '#0A2F2F';
            ctx.font = '700 16px Inter, sans-serif';
            ctx.fillText('Dijital Profil QR', canvas.width / 2, 456);

            let printableUrl = String(urlText || '').trim();
            if (printableUrl.length > 52) {
                printableUrl = printableUrl.slice(0, 49) + '...';
            }
            ctx.fillStyle = '#64748B';
            ctx.font = '600 14px Inter, sans-serif';
            ctx.fillText(printableUrl, canvas.width / 2, 482);

            ctx.fillStyle = '#64748B';
            ctx.font = '500 13px Inter, sans-serif';
            ctx.fillText('Bu QR kodu okutarak dijital kartvizite ulaşabilirsiniz.', canvas.width / 2, 550);

            const pngBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 0.96));
            if (!pngBlob) {
                throw new Error('canvas_to_blob_failed');
            }

            const downloadUrl = URL.createObjectURL(pngBlob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = `${sanitizeFileName(fileStem)}-qr-kartvizit.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(downloadUrl);
        }

        async function downloadShareQrCard() {
            if (!SHARE_QR_TARGET) return;

            const button = shareQrDownloadBtnEl;
            const originalLabel = button ? button.innerHTML : '';
            if (button) {
                button.disabled = true;
                button.style.opacity = '0.75';
            }

            try {
                let qrBlob = null;
                if (typeof QRCodeStyling !== 'undefined') {
                    const tempQr = new QRCodeStyling(buildQrOptions({
                        width: 360,
                        height: 360,
                        data: SHARE_QR_TARGET
                    }));
                    qrBlob = await tempQr.getRawData('png');
                } else {
                    const fallbackResponse = await fetch(`https://api.qrserver.com/v1/create-qr-code/?size=420x420&data=${encodeURIComponent(SHARE_QR_TARGET)}`);
                    qrBlob = await fallbackResponse.blob();
                }

                await composeQrCardAndDownload({
                    qrBlob,
                    title: 'Dijital Kartvizit',
                    subtitle: SHARE_QR_PROFILE_NAME || 'Profil QR',
                    urlText: SHARE_QR_TARGET,
                    fileStem: SHARE_QR_FILE_STEM || 'qr-kartvizit'
                });

                if (button) {
                    button.innerHTML = '<i data-lucide="check" style="width:14px; vertical-align:middle; margin-right:4px;"></i> İndirildi';
                    button.style.background = '#ecfdf5';
                    button.style.color = '#166534';
                    button.style.borderColor = '#86efac';
                    lucide.createIcons();
                    setTimeout(() => {
                        button.innerHTML = originalLabel;
                        button.style.background = '';
                        button.style.color = '';
                        button.style.borderColor = '';
                        button.style.opacity = '';
                        button.disabled = false;
                        lucide.createIcons();
                    }, 1800);
                    return;
                }
            } catch (error) {
                if (button) {
                    button.innerHTML = '<i data-lucide="alert-circle" style="width:14px; vertical-align:middle; margin-right:4px;"></i> Tekrar Dene';
                    button.style.background = '#fef2f2';
                    button.style.color = '#991b1b';
                    button.style.borderColor = '#fecaca';
                    button.style.opacity = '';
                    button.disabled = false;
                    lucide.createIcons();
                }
                return;
            }

            if (button) {
                button.innerHTML = originalLabel;
                button.style.opacity = '';
                button.disabled = false;
            }
        }
        window.downloadShareQrCard = downloadShareQrCard;

        async function downloadPlainQr(type) {
            let targetUrl = SHARE_QR_TARGET;
            let fileStem = SHARE_QR_FILE_STEM;
            let btn = null;

            if (type === 'share') {
                btn = document.getElementById('downloadPlainShareQrBtn');
            } else if (type === 'styled') {
                btn = document.getElementById('downloadPlainStyledQrBtn');
                targetUrl = QR_PREVIEW_TARGET;
                fileStem = `${SHARE_QR_FILE_STEM || 'qr-kartvizit'}-ozel`;
            }

            if (btn) {
                btn.disabled = true;
                btn.style.opacity = '0.7';
            }

            try {
                if (typeof QRCodeStyling !== 'undefined') {
                    const tempQr = new QRCodeStyling(buildQrOptions({
                        width: 1024,
                        height: 1024,
                        data: targetUrl
                    }));
                    await tempQr.download({
                        name: sanitizeFileName(fileStem),
                        extension: 'png'
                    });
                } else {
                    const fallbackUrl = `https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&data=${encodeURIComponent(targetUrl)}`;
                    const link = document.createElement('a');
                    link.href = fallbackUrl;
                    link.download = `${sanitizeFileName(fileStem)}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            } catch (error) {
                console.error('Plain QR download failed:', error);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            }
        }
        window.downloadPlainQr = downloadPlainQr;

        async function downloadStyledQrCard() {
            if (!qrStyling) return;
            const qrBlob = await qrStyling.getRawData('png');
            await composeQrCardAndDownload({
                qrBlob,
                title: 'Özelleştirilmiş QR',
                subtitle: SHARE_QR_PROFILE_NAME || 'Profil QR',
                urlText: QR_PREVIEW_TARGET,
                fileStem: `${SHARE_QR_FILE_STEM || 'qr-kartvizit'}-ozel`
            });
        }

        syncPickerAndHex(palettePickerEl, paletteHexEl);
        syncPickerAndHex(qrColorEl, qrColorHexEl, renderQrPreview);
        syncPickerAndHex(qrBgColorEl, qrBgColorHexEl, renderQrPreview);
        syncPickerAndHex(textColorPickerEl, textColorHexEl, setTextColor);
        syncPickerAndHex(avatarColorEl, avatarColorHexEl, setAvatarColor);
        setTextColor(textColorPickerEl ? textColorPickerEl.value : '');
        
        if (coverColorHexEl && coverColorEl) {
            coverColorHexEl.addEventListener('input', (e) => {
                const color = normalizeHexColor(e.target.value);
                if (color) {
                    coverColorEl.value = color;
                    setCoverColor(color);
                }
            });
        }

        if (qrDotStyleEl) qrDotStyleEl.addEventListener('change', renderQrPreview);
        if (qrCornerStyleEl) qrCornerStyleEl.addEventListener('change', renderQrPreview);
        if (qrFrameStyleEl) qrFrameStyleEl.addEventListener('change', renderQrPreview);

        if (qrDownloadBtnEl) {
            qrDownloadBtnEl.addEventListener('click', async () => {
                try {
                    await downloadStyledQrCard();
                } catch (error) {
                    // no-op
                }
            });
        }


        function handlePlatformChange(select) {
            const row = select.closest('.social-link-item');
            const customInput = row.querySelector('input[name="platform_customs[]"]');
            const iconWrapper = row.querySelector('.social-icon-wrapper');
            const logoRow = row.querySelector('.platform-logo-row');
             
            // Icon update
            const selectedOption = select.options[select.selectedIndex];
            const logoSrc = selectedOption.getAttribute('data-logo') || '';
            iconWrapper.innerHTML = `<img src="${logoSrc}" alt="" class="social-brand-logo">`;

            if (select.value === '__custom__') {
                customInput.style.display = 'block';
                if (logoRow) logoRow.style.display = 'flex';
            } else {
                customInput.style.display = 'none';
                if (logoRow) logoRow.style.display = 'none';
            }
        }

        function bindCustomLogoPreview(input) {
            if (!input) return;
            bindFileSelectionMeta(input, 'Henüz logo dosyası seçilmedi');
            input.addEventListener('change', (event) => {
                const target = event.target;
                const row = target.closest('.platform-logo-row');
                if (!row) return;
                const preview = row.querySelector('.platform-logo-preview');
                if (!preview) return;
                const file = target.files && target.files[0] ? target.files[0] : null;
                if (!file) {
                    preview.src = '';
                    preview.style.display = 'none';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (loadEvent) => {
                    preview.src = String(loadEvent.target && loadEvent.target.result ? loadEvent.target.result : '');
                    preview.style.display = preview.src !== '' ? 'block' : 'none';
                };
                reader.readAsDataURL(file);
            });
        }

        function addSocialLink() {
            const container = document.getElementById('social-links-container');
            const div = document.createElement('div');
            div.className = 'social-link-item';
            
            let optionsHtml = SOCIAL_OPTIONS.map(opt => 
                `<option value="${opt.value}" data-logo="${opt.logo}">${opt.label}</option>`
            ).join('');

            div.innerHTML = `
                <div class="social-icon-wrapper">
                    <img src="${SOCIAL_OPTIONS[0].logo}" alt="Instagram logosu" class="social-brand-logo">
                </div>
                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                    ${optionsHtml}
                </select>
                <div class="social-link-content">
                    <input type="text" name="urls[]" class="premium-input" placeholder="URL veya kullanıcı adı...">
                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;">
                    <input type="hidden" name="existing_platform_logos[]" value="">
                    <div class="platform-logo-row" style="display:none;">
                        <input type="file" name="platform_logos[]" class="premium-input platform-logo-input" accept="image/jpeg,image/png,image/webp" style="padding:0.55rem 0.7rem;">
                        <img src="" alt="Logo önizleme" class="platform-logo-preview" style="display:none;">
                        <span class="platform-logo-hint">Diğer platform için logo (opsiyonel)</span>
                    </div>
                </div>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
            `;
            container.appendChild(div);
            bindCustomLogoPreview(div.querySelector('.platform-logo-input'));
            lucide.createIcons();
        }

        Array.from(document.querySelectorAll('select[name="platforms[]"]')).forEach((select) => {
            handlePlatformChange(select);
        });
        Array.from(document.querySelectorAll('.platform-logo-input')).forEach((input) => {
            bindCustomLogoPreview(input);
        });

        function copyToClipboard() {
            const shareEl = document.getElementById('shareUrl');
            if (!shareEl) return;
            const url = shareEl.textContent || '';

            const onSuccess = () => {
                const btn = document.querySelector('button[onclick="copyToClipboard()"]');
                if (!btn) {
                    return;
                }
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" style="width:16px; margin-right:5px; vertical-align:middle;"></i> Kopyalandı!';
                btn.style.background = '#10b981';
                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                    lucide.createIcons();
                }, 2000);
            };

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(url).then(onSuccess).catch(() => {});
                return;
            }

            const tmpInput = document.createElement('input');
            tmpInput.value = url;
            document.body.appendChild(tmpInput);
            tmpInput.select();
            try {
                document.execCommand('copy');
                onSuccess();
            } catch (err) {
                // no-op
            } finally {
                document.body.removeChild(tmpInput);
            }
        }
        // Renk Seçicileri Başlat
        syncPickerAndHex(coverColorEl, coverColorHexEl, setCoverColor);
        syncPickerAndHex(textColorPickerEl, textColorHexEl, setTextColor);

        renderPaletteState();
        setCoverColor(coverColorEl ? coverColorEl.value : '');
        setAvatarInitial(avatarInitialInputEl ? avatarInitialInputEl.value : '');
        setTextColor(textColorPickerEl ? textColorPickerEl.value : '');
        renderQrPreview();
        renderShareInlineQr();
    </script>
</body>
</html>
