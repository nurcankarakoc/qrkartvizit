<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/subscription.php';

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
    // Profil yoksa taslak olustur (slug email'den turetilebilir)
    $seed_name = (string)($user['name'] ?? ($_SESSION['user_name'] ?? 'user'));
    $slug_base = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $seed_name), '-'));
    if ($slug_base === '') {
        $slug_base = 'user';
    }
    $slug = $slug_base . '-' . rand(100, 999);
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
$is_digital_profile_active_for_package = qrk_user_has_digital_access($pdo, $user_id, (string)($order['package'] ?? ''));
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

$social_platform_options = [
    ['value' => 'instagram', 'label' => 'Instagram', 'icon' => 'instagram'],
    ['value' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'linkedin'],
    ['value' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'message-circle'],
    ['value' => 'x', 'label' => 'X / Twitter', 'icon' => 'twitter'],
    ['value' => 'telegram', 'label' => 'Telegram', 'icon' => 'send'],
    ['value' => 'youtube', 'label' => 'YouTube', 'icon' => 'youtube'],
    ['value' => 'facebook', 'label' => 'Facebook', 'icon' => 'facebook'],
    ['value' => 'tiktok', 'label' => 'TikTok', 'icon' => 'music'],
    ['value' => 'website', 'label' => 'Web Sitesi', 'icon' => 'globe'],
    ['value' => 'mail', 'label' => 'E-posta', 'icon' => 'mail'],
    ['value' => 'phone', 'label' => 'Telefon', 'icon' => 'phone'],
    ['value' => 'maps', 'label' => 'Harita', 'icon' => 'map-pin'],
    ['value' => '__custom__', 'label' => 'Diger', 'icon' => 'link-2'],
];

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
} elseif (!empty($default_qr_style['cover_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$default_qr_style['cover_color'])) {
    $resolved_cover_color = strtoupper((string)$default_qr_style['cover_color']);
}
$resolved_cover_dark = darken_hex_color($resolved_cover_color, 0.34);

$resolved_avatar_color = $resolved_brand_color;
if (!empty($profile['avatar_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['avatar_color'])) {
    $resolved_avatar_color = strtoupper((string)$profile['avatar_color']);
} elseif (!empty($default_qr_style['avatar_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$default_qr_style['avatar_color'])) {
    $resolved_avatar_color = strtoupper((string)$default_qr_style['avatar_color']);
}
$resolved_avatar_text_color = contrast_text_for_hex($resolved_avatar_color);
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
];
$profile_error_messages = [
    'csrf' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.',
    'invalid_email' => 'Geçerli bir iş e-posta adresi girin.',
    'profile_not_found' => 'Profil kaydı bulunamadi. Lütfen tekrar giriş yapın.',
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
        .cover-color-box { display: flex; flex-direction: column; align-items: flex-end; gap: 0.55rem; padding: 0.75rem 0.9rem; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 5px 16px rgba(15, 23, 42, 0.06); min-width: 320px; }
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
        .avatar-upload { width: 140px; height: 140px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 12px 35px rgba(0,0,0,0.12); cursor: pointer; position: relative; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .avatar-inner { width: 100%; height: 100%; border-radius: 50%; overflow: hidden; position: relative; background: #f8fafc; display: flex; align-items: center; justify-content: center; border: 2px dashed #cbd5e1; transition: all 0.3s ease; }
        .avatar-upload:hover { transform: translateY(-5px); }
        .avatar-upload:hover .avatar-inner { border-color: var(--gold); }
        .avatar-inner img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; z-index: 2; }
        .avatar-upload .upload-overlay { position: absolute; inset: 0; background: rgba(10, 47, 47, 0.5); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; border-radius: 50%; color: #fff; z-index: 3; }
        .avatar-upload:hover .upload-overlay { opacity: 1; }
        .avatar-empty-state { position: relative; z-index: 1; display:flex; align-items:center; justify-content:center; width: 100%; height: 100%; border-radius: 50%; background: var(--avatar-base, #0A2F2F); color: var(--avatar-text, #FFFFFF); font-weight: 900; font-size: 2.2rem; letter-spacing: -0.8px; line-height: 1; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.32); transition: 0.3s; }
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
            z-index: 500;
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
            .share-card { flex-direction: column; text-align: center; padding: 2.5rem; gap: 2.5rem; }
            .qr-placeholder { transform: rotate(0); }
            .qr-style-grid { grid-template-columns: 1fr; }
            .qr-preview-frame { width: 100%; max-width: 260px; height: 240px; }
            .qr-preview-frame.frame-badge { width: 250px; height: 250px; }
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
                <li class="active"><a href="profile.php"><i data-lucide="user-cog"></i> Profilimi Düzenle</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
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
                    <div style="background: #fff; padding: 1rem; border-radius: 16px; border: 1px solid #eef2f6; text-align: center;">
                        <div id="shareQrInlineCanvas" data-fallback-src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($public_profile_url); ?>" aria-label="Paylaşım QR kodu" style="width: 140px; height: 140px; display: block; margin: 0 auto; border-radius: 8px; overflow: hidden;"></div>
                        <button type="button" id="downloadShareQrBtn" onclick="downloadShareQrCard()" style="display:block; width:100%; margin-top:0.75rem; color:#1e293b; font-weight:800; text-decoration:none; font-size:0.78rem; border:1px solid #dbe4ef; background:#f8fafc; border-radius:10px; padding:0.55rem 0.7rem; cursor:pointer;">
                            <i data-lucide="download" style="width:14px; vertical-align:middle; margin-right:4px;"></i> QR İndir
                        </button>
                        <div style="margin-top:0.45rem; font-size:0.72rem; color:#94a3b8; font-weight:600;">Kartvizit için optimize edilmiş PNG</div>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../processes/profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm">
                <?php echo csrf_input(); ?>
                
                <div class="profile-hero">
                    <div class="cover-upload" id="coverUploadBox" style="--cover-base: <?php echo htmlspecialchars($resolved_cover_color); ?>; --cover-dark: <?php echo htmlspecialchars($resolved_cover_dark); ?>;" onclick="document.getElementById('cover_photo').click()">
                        <?php if(!empty($profile['cover_photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($profile['cover_photo']); ?>" id="coverPreview">
                        <?php else: ?>
                            <div class="cover-empty-state" id="coverEmptyState">
                                <i data-lucide="image"></i>
                                <span>Kapak Fotoğrafı Ekle</span>
                            </div>
                            <img src="" id="coverPreview" style="display:none;">
                        <?php endif; ?>
                        
                        <div class="upload-overlay">
                            <i data-lucide="camera" style="width:28px; height:28px;"></i>
                            <span>Değiştir</span>
                        </div>
                        <input type="file" id="cover_photo" name="cover_photo" hidden onchange="previewFile(this, 'coverPreview')" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="cover-controls">
                        <div class="cover-color-box">
                            <div class="cover-color-head">
                                <label for="cover_color">Kapak Rengi</label>
                                <div class="cover-color-current">
                                    <span class="cover-color-swatch" id="coverColorSwatch"></span>
                                    <span class="cover-color-picker-wrap">
                                        <span class="cover-color-advanced-btn">Özel Renk</span>
                                        <input type="color" id="cover_color" name="cover_color" value="<?php echo htmlspecialchars($resolved_cover_color); ?>" class="cover-color-native-input" aria-label="Özel kapak rengi">
                                    </span>
                                </div>
                            </div>
                            <div class="cover-preset-list">
                                <button type="button" class="cover-preset-btn" data-color="#0A2F2F" style="background:#0A2F2F;" aria-label="Koyu petrol"></button>
                                <button type="button" class="cover-preset-btn" data-color="#0F766E" style="background:#0F766E;" aria-label="Yeşil mavi"></button>
                                <button type="button" class="cover-preset-btn" data-color="#1D4ED8" style="background:#1D4ED8;" aria-label="Mavi"></button>
                                <button type="button" class="cover-preset-btn" data-color="#4338CA" style="background:#4338CA;" aria-label="İndigo"></button>
                                <button type="button" class="cover-preset-btn" data-color="#7C3AED" style="background:#7C3AED;" aria-label="Mor"></button>
                                <button type="button" class="cover-preset-btn" data-color="#BE123C" style="background:#BE123C;" aria-label="Bordo"></button>
                                <button type="button" class="cover-preset-btn" data-color="#B45309" style="background:#B45309;" aria-label="Amber"></button>
                                <button type="button" class="cover-preset-btn" data-color="#374151" style="background:#374151;" aria-label="Antrasit"></button>
                            </div>
                        </div>
                    </div>

                    <div class="avatar-upload-container">
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
                    <div class="avatar-initial-settings">
                        <div class="avatar-initial-settings-head">
                            <strong>Fotoğraf Yoksa Profil Simgesi</strong>
                            <span>Baş harf ve renk seçebilirsiniz</span>
                        </div>
                        <div class="avatar-initial-settings-row">
                            <div class="avatar-initial-field">
                                <label for="avatar_initial" style="margin:0; font-size:0.78rem; color:#64748b; font-weight:800;">Baş Harf</label>
                                <input type="text" id="avatar_initial" name="avatar_initial" maxlength="2" class="premium-input" value="<?php echo htmlspecialchars($resolved_avatar_initial); ?>">
                            </div>
                            <div class="avatar-color-current">
                                <span class="avatar-color-swatch" id="avatarColorSwatch"></span>
                                <span class="cover-color-picker-wrap">
                                    <span class="cover-color-advanced-btn">Özel Renk</span>
                                    <input type="color" id="avatar_color" name="avatar_color" value="<?php echo htmlspecialchars($resolved_avatar_color); ?>" class="cover-color-native-input" aria-label="Özel profil simgesi rengi">
                                </span>
                            </div>
                            <div class="avatar-preset-list">
                                <button type="button" class="avatar-preset-btn" data-color="#0A2F2F" style="background:#0A2F2F;" aria-label="Koyu petrol"></button>
                                <button type="button" class="avatar-preset-btn" data-color="#0F766E" style="background:#0F766E;" aria-label="Yeşil mavi"></button>
                                <button type="button" class="avatar-preset-btn" data-color="#1D4ED8" style="background:#1D4ED8;" aria-label="Mavi"></button>
                                <button type="button" class="avatar-preset-btn" data-color="#7C3AED" style="background:#7C3AED;" aria-label="Mor"></button>
                                <button type="button" class="avatar-preset-btn" data-color="#BE123C" style="background:#BE123C;" aria-label="Bordo"></button>
                                <button type="button" class="avatar-preset-btn" data-color="#374151" style="background:#374151;" aria-label="Antrasit"></button>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <label>Şirket Adı</label>
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

                <div class="form-section">
                    <h3 class="section-title"><i data-lucide="palette"></i> Marka Kimliği</h3>
                    <input type="hidden" id="brand_color" name="brand_color" value="<?php echo htmlspecialchars($resolved_cover_color); ?>">

                    <div class="palette-builder">
                        <div class="palette-builder-head">
                            <strong style="color: var(--navy-blue);">Marka Renk Paleti</strong>
                            <p>Müşteri isteklerine göre birden fazla renk ekleyebilirsiniz (en fazla 6).</p>
                        </div>
                        <div class="palette-input-row">
                            <input type="color" id="palette_color_picker" value="<?php echo htmlspecialchars($resolved_cover_color); ?>" style="width:46px; height:46px; border:none; border-radius:12px; cursor:pointer; padding:0;">
                            <input type="text" id="palette_color_hex" maxlength="7" class="premium-input" style="width:120px; text-align:center; padding:0.72rem; font-family:monospace;" value="<?php echo htmlspecialchars($resolved_cover_color); ?>">
                            <button type="button" class="btn-palette-add" onclick="addPaletteColor()">Renk Ekle</button>
                        </div>
                        <div id="paletteColorList" class="palette-color-list"></div>
                        <input type="hidden" name="brand_palette" id="brand_palette" value="<?php echo htmlspecialchars((string)json_encode($resolved_brand_palette, JSON_UNESCAPED_UNICODE)); ?>">
                    </div>

                    <div class="qr-style-grid">
                        <div class="qr-style-controls">
                            <h4 style="margin:0 0 0.8rem; color: var(--navy-blue); font-size:1rem;">QR Özelleştirme</h4>
                            <div class="control-group">
                                <label for="qr_color">QR Ana Renk</label>
                                <div class="control-row">
                                    <input type="color" id="qr_color" name="qr_color" value="<?php echo htmlspecialchars((string)$default_qr_style['qr_color']); ?>" style="width:44px; height:44px; border:none; border-radius:10px; cursor:pointer;">
                                    <input type="text" id="qr_color_hex" class="premium-input" maxlength="7" value="<?php echo htmlspecialchars((string)$default_qr_style['qr_color']); ?>" style="font-family:monospace;">
                                </div>
                            </div>
                            <div class="control-group">
                                <label for="qr_bg_color">QR Arka Plan Rengi</label>
                                <div class="control-row">
                                    <input type="color" id="qr_bg_color" name="qr_bg_color" value="<?php echo htmlspecialchars((string)$default_qr_style['qr_bg_color']); ?>" style="width:44px; height:44px; border:none; border-radius:10px; cursor:pointer;">
                                    <input type="text" id="qr_bg_color_hex" class="premium-input" maxlength="7" value="<?php echo htmlspecialchars((string)$default_qr_style['qr_bg_color']); ?>" style="font-family:monospace;">
                                </div>
                            </div>
                            <div class="control-group">
                                <label for="qr_dot_style">QR Nokta Şekli</label>
                                <select id="qr_dot_style" name="qr_dot_style" class="premium-input">
                                    <option value="square" <?php echo $default_qr_style['qr_dot_style'] === 'square' ? 'selected' : ''; ?>>Kare</option>
                                    <option value="dots" <?php echo $default_qr_style['qr_dot_style'] === 'dots' ? 'selected' : ''; ?>>Nokta</option>
                                    <option value="rounded" <?php echo $default_qr_style['qr_dot_style'] === 'rounded' ? 'selected' : ''; ?>>Yuvarlatılmış</option>
                                    <option value="classy" <?php echo $default_qr_style['qr_dot_style'] === 'classy' ? 'selected' : ''; ?>>Classy</option>
                                    <option value="classy-rounded" <?php echo $default_qr_style['qr_dot_style'] === 'classy-rounded' ? 'selected' : ''; ?>>Classy Rounded</option>
                                    <option value="extra-rounded" <?php echo $default_qr_style['qr_dot_style'] === 'extra-rounded' ? 'selected' : ''; ?>>Extra Rounded</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label for="qr_corner_style">QR Göz Şekli</label>
                                <select id="qr_corner_style" name="qr_corner_style" class="premium-input">
                                    <option value="square" <?php echo $default_qr_style['qr_corner_style'] === 'square' ? 'selected' : ''; ?>>Kare</option>
                                    <option value="dot" <?php echo $default_qr_style['qr_corner_style'] === 'dot' ? 'selected' : ''; ?>>Nokta</option>
                                    <option value="extra-rounded" <?php echo $default_qr_style['qr_corner_style'] === 'extra-rounded' ? 'selected' : ''; ?>>Yuvarlak</option>
                                </select>
                            </div>
                            <div class="control-group">
                                <label for="qr_frame_style">QR Çerçeve Stili</label>
                                <select id="qr_frame_style" name="qr_frame_style" class="premium-input">
                                    <option value="classic" <?php echo $default_qr_style['qr_frame_style'] === 'classic' ? 'selected' : ''; ?>>Klasik</option>
                                    <option value="soft" <?php echo $default_qr_style['qr_frame_style'] === 'soft' ? 'selected' : ''; ?>>Soft</option>
                                    <option value="badge" <?php echo $default_qr_style['qr_frame_style'] === 'badge' ? 'selected' : ''; ?>>Rozet</option>
                                    <option value="none" <?php echo $default_qr_style['qr_frame_style'] === 'none' ? 'selected' : ''; ?>>Çerçevesiz</option>
                                </select>
                            </div>
                        </div>
                        <div class="qr-style-preview-card">
                            <h4 style="margin:0 0 0.8rem; color: var(--navy-blue); font-size:1rem; text-align:center;">Canlı QR Önizleme</h4>
                            <div id="qrPreviewFrame" class="qr-preview-frame frame-classic">
                                <div id="qrPreviewCanvas" class="qr-preview-canvas"></div>
                            </div>
                            <div class="qr-preview-actions">
                                <button type="button" class="btn-qr-download" id="downloadStyledQrBtn">QR'i PNG indir</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title"><i data-lucide="share-2"></i> Sosyal Medya & Linkler</h3>
                    <div id="social-links-container">
                        <?php if(empty($links)): ?>
                            <div class="social-link-item">
                                <div class="social-icon-wrapper">
                                    <i data-lucide="instagram"></i>
                                </div>
                                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                    <?php foreach ($social_platform_options as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-icon="<?php echo $opt['icon']; ?>" <?php echo $opt['value'] === 'instagram' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opt['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="social-link-content">
                                    <input type="text" name="urls[]" class="premium-input" placeholder="Linkinizi veya kullanıcı adınızı yapıştırın...">
                                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;" disabled>
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
                                    
                                    $platform_info = array_filter($social_platform_options, fn($o) => $o['value'] === $selected_platform);
                                    $icon = !empty($platform_info) ? reset($platform_info)['icon'] : 'link-2';
                                ?>
                                <div class="social-link-item">
                                    <div class="social-icon-wrapper">
                                        <i data-lucide="<?php echo $icon; ?>"></i>
                                    </div>
                                    <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                        <?php foreach ($social_platform_options as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-icon="<?php echo $opt['icon']; ?>" <?php echo $opt['value'] === $selected_platform ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="social-link-content">
                                        <input type="text" name="urls[]" class="premium-input" value="<?php echo htmlspecialchars($link['url']); ?>" placeholder="Linkinizi buraya yapıştırın...">
                                        <input type="text" name="platform_customs[]" class="premium-input" value="<?php echo htmlspecialchars($custom_platform_value); ?>" <?php echo $is_custom_platform ? '' : 'style="display:none; margin-top:0.75rem;" disabled'; ?>>
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
            if (avatarColorSwatchEl) avatarColorSwatchEl.style.backgroundColor = normalized;
            if (avatarInnerBoxEl) {
                avatarInnerBoxEl.style.setProperty('--avatar-base', normalized);
                avatarInnerBoxEl.style.setProperty('--avatar-text', getContrastTextColor(normalized));
            }
            avatarPresetButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.color === normalized);
            });
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

        if (coverColorEl) {
            coverColorEl.addEventListener('input', (event) => {
                setCoverColor(event.target.value);
            });
        }
        coverPresetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setCoverColor(button.dataset.color || '');
            });
        });
        if (avatarColorEl) {
            avatarColorEl.addEventListener('input', (event) => {
                setAvatarColor(event.target.value);
            });
        }
        if (avatarInitialInputEl) {
            avatarInitialInputEl.addEventListener('input', (event) => {
                setAvatarInitial(event.target.value);
            });
            avatarInitialInputEl.addEventListener('blur', (event) => {
                setAvatarInitial(event.target.value);
            });
        }
        avatarPresetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setAvatarColor(button.dataset.color || '');
            });
        });

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

        loadPaletteState();
        renderPaletteState();
        setCoverColor(coverColorEl ? coverColorEl.value : '');
        setAvatarInitial(avatarInitialInputEl ? avatarInitialInputEl.value : '');
        setAvatarColor(avatarColorEl ? avatarColorEl.value : '');
        renderQrPreview();

        function handlePlatformChange(select) {
            const row = select.closest('.social-link-item');
            const customInput = row.querySelector('input[name="platform_customs[]"]');
            const iconWrapper = row.querySelector('.social-icon-wrapper');
            
            // Icon update
            const selectedOption = select.options[select.selectedIndex];
            const iconName = selectedOption.getAttribute('data-icon') || 'link-2';
            iconWrapper.innerHTML = `<i data-lucide="${iconName}"></i>`;
            lucide.createIcons();

            if (select.value === '__custom__') {
                customInput.style.display = 'block';
                customInput.disabled = false;
            } else {
                customInput.style.display = 'none';
                customInput.disabled = true;
            }
        }

        function addSocialLink() {
            const container = document.getElementById('social-links-container');
            const div = document.createElement('div');
            div.className = 'social-link-item';
            
            let optionsHtml = SOCIAL_OPTIONS.map(opt => 
                `<option value="${opt.value}" data-icon="${opt.icon}">${opt.label}</option>`
            ).join('');

            div.innerHTML = `
                <div class="social-icon-wrapper">
                    <i data-lucide="instagram"></i>
                </div>
                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                    ${optionsHtml}
                </select>
                <div class="social-link-content">
                    <input type="text" name="urls[]" class="premium-input" placeholder="URL veya kullanıcı adı...">
                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;" disabled>
                </div>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
            `;
            container.appendChild(div);
            lucide.createIcons();
        }

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
    </script>
</body>
</html>
