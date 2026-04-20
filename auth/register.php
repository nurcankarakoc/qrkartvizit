<?php
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';
ensure_session_started();

$register_error_key = trim((string)($_GET['error'] ?? ''));
$register_error_map = [
    'csrf' => 'Güvenlik doğrulaması başarısız oldu. Lütfen formu tekrar gönderin.',
    'required' => 'Lütfen zorunlu alanları eksiksiz doldurun.',
    'invalid_email' => 'Geçerli bir e-posta adresi girin.',
    'kvkk' => 'Devam etmek için KVKK onayı gereklidir.',
    'email_exists' => 'Bu e-posta adresi zaten kayıtlı.',
    'logo_upload_error' => 'Logo yüklenirken bir hata oluştu. Lütfen dosyayı tekrar seçin.',
    'logo_too_large' => 'Logo dosyası en fazla 5 MB olabilir.',
    'logo_invalid_type' => 'Logo için sadece JPG, PNG veya WEBP dosyası kabul edilir.',
    'logo_dir_failed' => 'Yükleme klasörü oluşturulamadı. Lütfen daha sonra tekrar deneyin.',
    'logo_move_failed' => 'Logo dosyası kaydedilemedi. Farklı bir dosya ile tekrar deneyin.',
    'profile_photo_upload_error' => 'Profil fotoğrafı yüklenirken bir hata oluştu. Lütfen dosyayı tekrar seçin.',
    'profile_photo_too_large' => 'Profil fotoğrafı en fazla 5 MB olabilir.',
    'profile_photo_invalid_type' => 'Profil fotoğrafı için sadece JPG, PNG veya WEBP dosyası kabul edilir.',
    'profile_photo_dir_failed' => 'Profil fotoğrafı klasörü oluşturulamadı. Lütfen daha sonra tekrar deneyin.',
    'profile_photo_move_failed' => 'Profil fotoğrafı kaydedilemedi. Farklı bir dosya ile tekrar deneyin.',
    'register_failed' => 'Kayıt sırasında beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.',
];
$register_error_message = $register_error_map[$register_error_key] ?? '';

$register_old_input = $_SESSION['register_old_input'] ?? [];
if (!is_array($register_old_input)) {
    $register_old_input = [];
}
unset($_SESSION['register_old_input']);

$register_error_step = (int)($_SESSION['register_error_step'] ?? 1);
unset($_SESSION['register_error_step']);
$register_initial_step = 1;

if (!function_exists('register_old')) {
    function register_old(string $key, string $default = ''): string
    {
        global $register_old_input;
        $value = $register_old_input[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

$register_package_catalog = qrk_get_all_package_definitions($pdo);
$register_active_package_catalog = array_filter(
    $register_package_catalog,
    static fn(array $package): bool => (bool)($package['is_active'] ?? true)
);
if ($register_active_package_catalog === []) {
    $register_active_package_catalog = $register_package_catalog;
}

$register_available_package_slugs = array_keys($register_active_package_catalog);
$register_selected_package = register_old('package', 'smart');
if (!in_array($register_selected_package, $register_available_package_slugs, true)) {
    $register_selected_package = in_array('smart', $register_available_package_slugs, true)
        ? 'smart'
        : (string)(array_key_first($register_active_package_catalog) ?? 'classic');
}

$register_selected_package_meta = $register_active_package_catalog[$register_selected_package] ?? reset($register_active_package_catalog);

$register_social_platforms = $register_old_input['social_platforms'] ?? [];
$register_social_urls = $register_old_input['social_urls'] ?? [];
$register_social_customs = $register_old_input['social_platform_customs'] ?? [];
if (!is_array($register_social_platforms)) {
    $register_social_platforms = [];
}
if (!is_array($register_social_urls)) {
    $register_social_urls = [];
}
if (!is_array($register_social_customs)) {
    $register_social_customs = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başvuru Yap — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --navy-blue: #0A2F2F;
            --navy-dark: #072424;
            --gold: #A6803F;
            --gold-light: #C5A059;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(166, 128, 63, 0.10), transparent 28%),
                radial-gradient(circle at bottom right, rgba(10, 47, 47, 0.12), transparent 34%),
                linear-gradient(180deg, #f4f1ea 0%, #f8fafc 36%, #eef3f7 100%);
            color: #1e293b;
            font-family: 'Inter', sans-serif;
        }

        .auth-layout {
            display: grid;
            grid-template-columns: 1.2fr 2fr;
            min-height: 100vh;
        }

        .auth-sidebar {
            background:
                radial-gradient(circle at 18% 18%, rgba(197, 160, 89, 0.22), transparent 24%),
                radial-gradient(circle at 82% 22%, rgba(255, 255, 255, 0.08), transparent 18%),
                linear-gradient(160deg, #061c1c 0%, #0a2f2f 52%, #103c3c 100%);
            color: #fff;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            position: relative;
            overflow: hidden;
        }

        .auth-sidebar-content {
            margin-top: 2rem;
            position: relative;
            z-index: 2;
        }

        .auth-sidebar h2 {
            font-size: 2.45rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            line-height: 1.12;
            letter-spacing: -0.03em;
        }

        .sidebar-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            border: 1px solid rgba(166, 128, 63, 0.3);
            background: rgba(166, 128, 63, 0.1);
            color: var(--gold-light);
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            box-shadow: 0 0 20px rgba(166, 128, 63, 0.15);
        }

        .benefit-list {
            list-style: none;
            margin-top: 2rem;
        }

        .benefit-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .benefit-list i {
            color: var(--gold);
        }

        .sidebar-proof-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
            margin-top: 2rem;
        }

        .sidebar-proof-card {
            padding: 0.75rem 1rem;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .sidebar-proof-card:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-3px);
            border-color: rgba(166,128,63,0.3);
        }

        .sidebar-proof-value {
            font-size: 1.25rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.03em;
        }

        .sidebar-proof-label {
            margin-top: 0.2rem;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.72);
            line-height: 1.4;
        }

        .sidebar-quote {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(180deg, rgba(255,255,255,0.07), rgba(255,255,255,0.03));
            color: rgba(255,255,255,0.8);
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .auth-main {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            overflow-y: auto;
            max-height: 100vh;
        }

        .form-container {
            width: 100%;
            max-width: 860px;
            padding: 2.25rem;
            border-radius: 32px;
            border: 1px solid rgba(166, 128, 63, 0.15);
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 
                0 30px 100px rgba(10, 47, 47, 0.1),
                0 0 50px rgba(166, 128, 63, 0.08);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(166, 128, 63, 0.12), transparent 22%),
                linear-gradient(180deg, rgba(255,255,255,0.68), rgba(255,255,255,0.92));
            pointer-events: none;
        }

        .form-container > * {
            position: relative;
            z-index: 1;
        }

        /* STEPPER STYLES */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            position: relative;
            padding: 0.6rem 0.75rem;
            border-radius: 20px;
            border: 1px solid #edf2f7;
            background: rgba(248, 250, 252, 0.92);
        }

        .stepper::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #f1f5f9;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: #94a3b8;
            transition: all 0.3s;
        }

        .step-item.active .step-dot {
            background: var(--navy-blue);
            border-color: var(--navy-blue);
            color: #fff;
            box-shadow: 0 0 0 6px rgba(10, 47, 47, 0.1);
        }

        .step-item.completed .step-dot {
            background: var(--gold);
            border-color: var(--gold);
            color: #fff;
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .step-item.active .step-label { color: var(--navy-blue); }

        .intro-stage {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.9fr);
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .intro-primary,
        .intro-secondary {
            border-radius: 20px;
            padding: 1rem;
        }

        .intro-primary {
            background:
                radial-gradient(circle at top left, rgba(166, 128, 63, 0.16), transparent 24%),
                linear-gradient(155deg, #ffffff 0%, #fbfcfd 44%, #f6f8fb 100%);
            border: 1px solid #e2e8f0;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.05);
        }

        .intro-secondary {
            background:
                linear-gradient(180deg, rgba(10, 47, 47, 0.96), rgba(8, 34, 34, 0.96));
            border: 1px solid rgba(10, 47, 47, 0.18);
            color: #fff;
            box-shadow: 0 20px 40px rgba(6, 28, 28, 0.18);
        }

        .intro-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            background: rgba(166, 128, 63, 0.1);
            color: #8a6428;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .intro-lead {
            margin: 0.5rem 0 0;
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.6;
            max-width: 42rem;
        }

        .intro-value-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .intro-value-card {
            padding: 0.95rem 1rem;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            background: rgba(248, 250, 252, 0.88);
        }

        .intro-value-card strong {
            display: block;
            color: var(--navy-blue);
            font-size: 0.95rem;
            font-weight: 800;
        }

        .intro-value-card span {
            display: block;
            margin-top: 0.35rem;
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .intro-process-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
        }

        .intro-process-list {
            margin: 1rem 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.8rem;
        }

        .intro-process-item {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 0.8rem;
            align-items: flex-start;
        }

        .intro-process-badge {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            font-weight: 800;
        }

        .intro-process-copy strong {
            display: block;
            color: #fff;
            font-size: 0.92rem;
            font-weight: 800;
        }

        .intro-process-copy p {
            margin: 0.15rem 0 0;
            color: rgba(255,255,255,0.72);
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .intro-note {
            margin-top: 0.75rem;
            padding: 0.85rem 1.1rem;
            border-radius: 18px;
            border: 1px solid rgba(166, 128, 63, 0.25);
            background: linear-gradient(135deg, #fffcf5 0%, #fffefb 100%);
            color: #7c5d23;
            font-size: 0.82rem;
            line-height: 1.6;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(166, 128, 63, 0.05);
        }

        .form-header {
            margin-bottom: 1rem;
        }

        .form-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
            color: var(--navy-blue);
        }

        .form-header p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-error-banner {
            margin: 0 0 1rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .package-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .package-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.85rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            text-align: left;
            min-height: auto;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            background: #fff;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04);
        }

        .package-card input {
            position: absolute;
            opacity: 0;
        }

        .package-card:hover {
            border-color: #d1d9e6;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
        }

        .package-card.active {
            border-color: var(--gold);
            background: #fffdf7;
            box-shadow:
                0 0 0 2px rgba(202, 138, 4, 0.28),
                0 12px 24px rgba(202, 138, 4, 0.14);
        }

        .package-card:focus-within {
            border-color: #d4a748;
            box-shadow:
                0 0 0 2px rgba(212, 167, 72, 0.24),
                0 10px 20px rgba(15, 23, 42, 0.08);
        }

        .package-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .package-card .price {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--navy-blue);
        }

        .package-subtitle {
            font-size: 0.74rem;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .package-meta-list {
            list-style: none;
            margin: 0.2rem 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .package-meta-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.72rem;
            color: #475569;
            line-height: 1.25;
        }

        .package-meta-list li::before {
            content: '';
            width: 7px;
            height: 7px;
            min-width: 7px;
            border-radius: 50%;
            background: var(--gold);
            margin-top: 0.32rem;
        }

        .package-badge {
            align-self: flex-start;
            font-size: 0.68rem;
            font-weight: 800;
            color: #fff;
            background: var(--navy-blue);
            border-radius: 999px;
            padding: 0.22rem 0.55rem;
            letter-spacing: 0.4px;
        }

        .package-note {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 0;
            margin-bottom: 0.9rem;
            line-height: 1.4;
        }

        #step-id-3,
        #step-3 {
            display: none !important;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1.1rem;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: all 0.2s ease;
            background: #fdfdfd;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            background: #fff;
            box-shadow: 
                0 0 0 4px rgba(166, 128, 63, 0.08),
                0 10px 20px rgba(10, 47, 47, 0.03);
        }

        .file-upload-box {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: #f8fafc;
        }

        .file-upload-box:hover {
            border-color: var(--gold);
            background: rgba(166, 128, 63, 0.02);
        }

        .file-upload-box.dragover {
            border-color: var(--gold);
            background: rgba(166, 128, 63, 0.08);
        }

        .file-upload-box.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .file-upload-box.compact {
            padding: 1.25rem;
        }

        .file-upload-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-top: 0.85rem;
            flex-wrap: wrap;
        }

        .file-action-btn {
            border: none;
            background: var(--navy-blue);
            color: #fff;
            border-radius: 10px;
            padding: 0.5rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }

        .file-action-btn.secondary {
            background: #e2e8f0;
            color: #334155;
        }

        .file-action-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .file-preview-thumb {
            width: 76px;
            height: 76px;
            border-radius: 10px;
            object-fit: cover;
            margin: 0.55rem auto 0;
            border: 1px solid #cbd5e1;
            display: block;
            background: #fff;
        }

        .input-help-text {
            margin-top: 0.45rem;
            color: #64748b;
            font-size: 0.76rem;
            line-height: 1.4;
        }

        .theme-color-row {
            display: grid;
            grid-template-columns: 1fr 165px;
            gap: 0.75rem;
        }

        .theme-color-preview {
            margin-top: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .theme-color-swatch {
            width: 20px;
            height: 20px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            background: #0A2F2F;
        }

        .panel-config-box {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem 1rem 0.25rem;
            background: #f8fafc;
            margin-bottom: 1.25rem;
        }

        .panel-config-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.4rem;
        }

        .panel-config-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--navy-blue);
        }

        .panel-state-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.6rem;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .panel-state-badge.active {
            color: #14532d;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .panel-state-badge.inactive {
            color: #7f1d1d;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }

        .panel-state-text {
            margin: 0 0 0.9rem;
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.45;
        }

        .panel-disabled-note {
            margin-bottom: 0.9rem;
            padding: 0.75rem 0.85rem;
            border-radius: 10px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .panel-config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .panel-social-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .panel-social-row {
            display: grid;
            grid-template-columns: 190px 1fr auto;
            gap: 0.75rem;
        }

        .platform-custom-input {
            margin-top: 0.65rem;
        }

        .panel-social-remove {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #ef4444;
            border-radius: 12px;
            padding: 0.75rem 0.95rem;
            cursor: pointer;
            font-weight: 700;
        }

        .panel-social-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            margin-top: 0.25rem;
        }

        .panel-social-add {
            border: none;
            background: rgba(166, 128, 63, 0.15);
            color: #8a6428;
            border-radius: 10px;
            padding: 0.6rem 0.9rem;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .panel-social-help {
            font-size: 0.76rem;
            color: #64748b;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .checkbox-group label {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }

        .kvkk-link {
            color: var(--gold);
            font-weight: 700;
            text-decoration: none;
        }

        .kvkk-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 2000;
        }

        .kvkk-modal-overlay.open {
            display: flex;
        }

        .kvkk-modal {
            width: 100%;
            max-width: 760px;
            max-height: 86vh;
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.25);
            padding: 1.25rem;
        }

        .kvkk-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .kvkk-modal-title {
            margin: 0;
            font-size: 1.1rem;
            color: var(--navy-blue);
            font-weight: 800;
        }

        .kvkk-modal-close {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.35rem 0.6rem;
            font-size: 0.82rem;
            cursor: pointer;
        }

        .kvkk-modal-content p {
            margin: 0 0 0.7rem;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.55;
        }

        .file-preview-modal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            max-height: 60vh;
            overflow: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }

        .file-preview-modal-body img {
            max-width: 100%;
            max-height: 56vh;
            object-fit: contain;
            display: block;
        }

        .step-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-register-submit, .btn-next {
            flex: 2;
            background:
                linear-gradient(135deg, #082a2a, #0f4343) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98), rgba(166, 128, 63, 0.95)) border-box;
            color: #fff;
            padding: 1.1rem 1rem;
            border: 1px solid transparent;
            border-radius: 18px;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 15px 35px rgba(7, 36, 36, 0.2),
                0 0 15px rgba(166, 128, 63, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .btn-prev {
            flex: 1;
            background: #fff;
            color: var(--navy-blue);
            padding: 1.1rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-link-row {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.93rem;
            color: #64748b;
        }

        .btn-register-submit:hover, .btn-next:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 32px rgba(7, 36, 36, 0.22);
            background:
                linear-gradient(135deg, #0b3434, #145353) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 1), rgba(166, 128, 63, 1)) border-box;
        }

        .btn-prev:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .back-link {
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
            margin-bottom: 2rem;
        }

        .back-link:hover { color: #fff; }

        @media (max-width: 1024px) {
            .auth-layout { grid-template-columns: 1fr; }
            .auth-sidebar { display: none; }
            .auth-main { padding: 3rem 1.5rem; }
            .intro-stage { grid-template-columns: 1fr; }
        }

        @media (max-height: 900px) and (min-width: 1025px) {
            .auth-main { padding-top: 5.2rem; padding-bottom: 0.2rem; }
            .form-container { padding-top: 0.2rem; }
            .stepper { margin-bottom: 1.25rem; }
            .form-header h1 { font-size: 1.95rem; }
            .package-grid { margin-bottom: 0.75rem; }
            .package-card { min-height: 190px; }
            .package-meta-list li { font-size: 0.7rem; }
            .login-link-row { margin-top: 0.75rem; }
        }

        @media (max-width: 600px) {
            .package-grid { grid-template-columns: 1fr; }
            .package-card { min-height: auto; }
            .form-grid { grid-template-columns: 1fr; }
            .intro-value-grid { grid-template-columns: 1fr; }
            .panel-config-grid { grid-template-columns: 1fr; }
            .panel-social-row { grid-template-columns: 1fr; }
            .panel-social-actions { flex-direction: column; align-items: flex-start; }
            .theme-color-row { grid-template-columns: 1fr; }
            .file-upload-actions { justify-content: flex-start; }
            .stepper { margin-bottom: 2rem; }
            .step-label { display: none; }
        }

        @media (max-width: 768px) {
            .auth-main {
                padding: 1.25rem 1rem calc(2rem + env(safe-area-inset-bottom, 0px));
            }
            .form-container { padding: 1.1rem; }
            .form-header h1 { font-size: 2rem; }
            .step-actions { flex-direction: column; }
            .btn-register-submit, .btn-next, .btn-prev { width: 100%; min-height: 44px; }
            .stepper { overflow-x: auto; gap: 0.75rem; padding-bottom: 0.5rem; }
            .step-item { min-width: 90px; }
        }

        @media (max-width: 480px) {
            .form-header h1 { font-size: 1.75rem; }
            .package-card { padding: 1rem; }
            .file-upload-box { padding: 1.25rem; }
        }

        @media (max-width: 360px) {
            .auth-main { padding: 1rem 0.75rem calc(1.5rem + env(safe-area-inset-bottom, 0px)); }
            .form-container { padding: 0.85rem; }
            .form-header h1 { font-size: 1.5rem; }
            .form-header p { font-size: 0.88rem; }
            .stepper { gap: 0.5rem; }
        }

        input, select, textarea { font-size: 16px !important; }
    </style>
</head>
<body>

    <div class="auth-layout">
        <div class="auth-sidebar">
            <a href="../index.php" class="back-link">
                    <i data-lucide="arrow-left" style="width: 16px;"></i> Anasayfaya Dön
            </a>
                <div class="auth-sidebar-content">
                    <span class="sidebar-kicker">Premium Müşteri Deneyimi</span>
                    <h2>Kurumsal kartvizit sürecinizi dijital kaliteyle başlatın</h2>
                    <p style="opacity: 0.8; font-size: 1.02rem; margin-top: 1rem; line-height: 1.8;">
                        Zerosoft; fiziksel kartvizit, dijital profil, QR paylaşımı ve yönetim panelini tek bir profesyonel deneyimde birleştirir.
                    </p>

                    <ul class="benefit-list">
                        <li><i data-lucide="check-circle-2"></i> Dinamik Profil Paneli</li>
                        <li><i data-lucide="check-circle-2"></i> Tek Tıkla Rehbere Kaydetme</li>
                        <li><i data-lucide="check-circle-2"></i> Otomatik QR Kod Üretimi</li>
                        <li><i data-lucide="check-circle-2"></i> Profesyonel Kartvizit Baskısı</li>
                    </ul>

                    <div class="sidebar-proof-grid">
                        <article class="sidebar-proof-card">
                            <div class="sidebar-proof-value">Tek panel</div>
                            <div class="sidebar-proof-label">Hesap, paket kararı ve sipariş süreci tek yerde yönetilir.</div>
                        </article>
                        <article class="sidebar-proof-card">
                            <div class="sidebar-proof-value">Şeffaf akış</div>
                            <div class="sidebar-proof-label">Paket içeriğini görmeden karar vermeniz beklenmez.</div>
                        </article>
                    </div>

                    <div class="sidebar-quote">
                        “İlk izlenim sadece tasarımla değil, kullanıcıya hissettirdiğiniz güvenle oluşur.”
                    </div>
                </div>
        </div>

        <main class="auth-main">
            <div class="form-container">
                <div class="stepper">
                    <div class="step-item active" id="step-id-1">
                        <div class="step-dot">1</div>
                        <div class="step-label">Hesap Oluştur</div>
                    </div>
                </div>

                <?php if ($register_error_message !== ''): ?>
                    <div class="form-error-banner"><?php echo htmlspecialchars($register_error_message); ?></div>
                <?php endif; ?>

                <form id="multi-step-form" action="../processes/register_process.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="digital_profile_enabled" id="digital-profile-enabled" value="1">
                    <input type="hidden" name="current_step" id="current-step-input" value="<?php echo (int)$register_initial_step; ?>">
                    
                    <!-- STEP 1: ACCOUNT -->
                    <div class="form-step active" id="step-1">
                        <div class="form-header">
                            <h1>Başvurunuzu oluşturun</h1>
                            <p>Temel bilgilerinizi girerek hesabınızı açın. Başvuruyu tamamladıktan sonra doğrudan paket seçimine geçecek, ardından satın alma adımından devam edeceksiniz.</p>
                        </div>

                        <div class="intro-primary" style="margin-bottom:1rem;">
                            <span class="intro-kicker">
                                <i data-lucide="shield-check" style="width:14px; height:14px;"></i>
                                Hızlı Başlangıç
                            </span>
                            <p class="intro-lead" style="margin-top:0.85rem;">
                                Bu adımda yalnızca hesabınızı açıyoruz. Başvurudan hemen sonra paket seçimi açılır ve satın alma akışı oradan ilerler. Böylece ilk adım kısa, temiz ve profesyonel kalır.
                            </p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Ad Soyad</label>
                                <input type="text" name="name" class="form-control" placeholder="Mehmet Yılmaz" value="<?php echo htmlspecialchars(register_old('name')); ?>">
                            </div>
                            <div class="form-group">
                                <label>E-posta Adresi</label>
                                <input type="email" name="email" class="form-control" placeholder="mehmet@email.com" value="<?php echo htmlspecialchars(register_old('email')); ?>">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Telefon Numarası</label>
                                <input type="tel" name="phone" class="form-control" placeholder="0532 000 00 00" value="<?php echo htmlspecialchars(register_old('phone')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Şifre Belirleyin</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="submit" class="btn-register-submit">
                                Hesabı Oluştur <i data-lucide="check" style="vertical-align: middle; margin-left: 0.5rem;"></i>
                            </button>
                        </div>

                        <div class="checkbox-group" style="margin-top: 1rem;">
                            <input type="checkbox" id="kvkk" name="kvkk_approved" value="1" required>
                            <label for="kvkk">
                                <a href="#" id="kvkk-open" class="kvkk-link">KVKK Aydınlatma Metni</a>'ni okudum ve onaylıyorum.
                            </label>
                        </div>
                    </div>

                    <p class="login-link-row">
                        Zaten üye misiniz? <a href="login.php" style="color: var(--gold); font-weight: 800; text-decoration: none;">Giriş Yap</a>
                    </p>
                </form>
            </div>
        </main>
    </div>

    <div class="kvkk-modal-overlay" id="kvkk-modal" aria-hidden="true">
        <div class="kvkk-modal" role="dialog" aria-modal="true" aria-labelledby="kvkk-modal-title">
            <div class="kvkk-modal-header">
                <h3 class="kvkk-modal-title" id="kvkk-modal-title">KVKK Aydınlatma Metni</h3>
                <button type="button" class="kvkk-modal-close" id="kvkk-close">Kapat</button>
            </div>
            <div class="kvkk-modal-content">
                <p>6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında, veri sorumlusu sıfatıyla Zerosoft tarafından ad-soyad, telefon, e-posta, şirket bilgisi, sipariş ve tasarım talepleriniz işlenmektedir.</p>
                <p>Kişisel verileriniz; üyelik oluşturma, sipariş yönetimi, müşteri destek süreçleri, faturalama ve dijital kartvizit hizmetinin sunulması amaçlarıyla sınırlı olarak işlenir.</p>
                <p>Verileriniz, yasal yükümlülüklerin yerine getirilmesi ve hizmetin yürütülmesi amacıyla anlaşmalı hizmet sağlayıcılarla ve kanunen yetkili kamu kurumlarıyla paylaşılabilir.</p>
                <p>Kanunun 11. maddesi uyarınca kişisel verilerinize ilişkin erişim, düzeltme, silme, işlemeyi kısıtlama ve itiraz haklarınızı kullanabilirsiniz.</p>
                <p>Başvurularınızı destek@zerosoft.com e-posta adresi üzerinden veya yazılı olarak iletebilirsiniz.</p>
            </div>
        </div>
    </div>

    <div class="kvkk-modal-overlay" id="file-preview-modal" aria-hidden="true">
        <div class="kvkk-modal" role="dialog" aria-modal="true" aria-labelledby="file-preview-title" style="max-width: min(90vw, 560px);">
            <div class="kvkk-modal-header">
                <h3 class="kvkk-modal-title" id="file-preview-title">Görsel Önizleme</h3>
                <button type="button" class="kvkk-modal-close" id="file-preview-close">Kapat</button>
            </div>
            <div class="file-preview-modal-body">
                <img id="file-preview-image" alt="Yüklenen görsel önizleme">
            </div>
        </div>
    </div>

    <script src="../assets/js/mobile-form.js"></script>
    <script>
        lucide.createIcons();

        let currentStep = <?php echo (int)$register_initial_step; ?>;
        const registerInitialStep = <?php echo (int)$register_initial_step; ?>;
        const oldRegisterSocialPlatforms = <?php echo json_encode(array_values($register_social_platforms), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const oldRegisterSocialUrls = <?php echo json_encode(array_values($register_social_urls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const oldRegisterSocialCustoms = <?php echo json_encode(array_values($register_social_customs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const registerPackageCatalog = <?php echo json_encode($register_active_package_catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const registerPackageNotes = <?php echo json_encode(array_map(static fn(array $package): string => (string)($package['register_note'] ?? ''), $register_active_package_catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const registerPackagePanelTexts = <?php echo json_encode(array_map(static fn(array $package): string => (string)($package['register_panel_text'] ?? ''), $register_active_package_catalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const digitalPackages = Object.keys(registerPackageCatalog).filter((slug) => Boolean(registerPackageCatalog[slug]?.has_digital_profile));
        const SOCIAL_PLATFORM_OPTIONS = [
            { value: 'instagram', label: 'Instagram' },
            { value: 'linkedin', label: 'LinkedIn' },
            { value: 'whatsapp', label: 'WhatsApp' },
            { value: 'x', label: 'X / Twitter' },
            { value: 'telegram', label: 'Telegram' },
            { value: 'youtube', label: 'YouTube' },
            { value: 'facebook', label: 'Facebook' },
            { value: 'tiktok', label: 'TikTok' },
            { value: 'twitch', label: 'Twitch' },
            { value: 'github', label: 'GitHub' },
            { value: 'behance', label: 'Behance' },
            { value: 'dribbble', label: 'Dribbble' },
            { value: 'medium', label: 'Medium' },
            { value: 'threads', label: 'Threads' },
            { value: 'snapchat', label: 'Snapchat' },
            { value: 'pinterest', label: 'Pinterest' },
            { value: 'website', label: 'Web Sitesi' },
            { value: 'mail', label: 'E-posta' },
            { value: 'phone', label: 'Telefon' },
            { value: 'maps', label: 'Harita Konumu' },
            { value: '__custom__', label: 'Diğer (Özel)' }
        ];

        function getSelectedPackage() {
            const selected = document.querySelector('input[name="package"]:checked');
            return selected ? selected.value : 'smart';
        }

        function getPreferredRegisterPackage() {
            if (registerPackageCatalog.smart) return 'smart';
            const available = Object.keys(registerPackageCatalog);
            return available.length > 0 ? available[0] : 'classic';
        }

        function renderPackageCards() {
            const packageGrid = document.querySelector('.package-grid');
            if (!packageGrid) return;

            const selectedPackage = getSelectedPackage() || getPreferredRegisterPackage();
            const cardsHtml = Object.entries(registerPackageCatalog).map(([slug, definition]) => {
                const badge = definition.register_badge
                    ? `<span class="package-badge">${escapeHtml(definition.register_badge)}</span>`
                    : '';
                const price = definition.register_price_text
                    ? `<span class="price">${escapeHtml(definition.register_price_text)}</span>`
                    : '';
                const features = Array.isArray(definition.register_features)
                    ? definition.register_features.map((feature) => `<li>${escapeHtml(feature)}</li>`).join('')
                    : '';

                return `
                    <label class="package-card ${selectedPackage === slug ? 'active' : ''}" onclick="selectPackage(this)">
                        <input type="radio" name="package" value="${escapeHtml(slug)}" ${selectedPackage === slug ? 'checked' : ''}>
                        ${badge}
                        <h4>${escapeHtml(definition.register_title || definition.label || slug)}</h4>
                        ${price}
                        <p class="package-subtitle">${escapeHtml(definition.register_subtitle || '')}</p>
                        <ul class="package-meta-list">${features}</ul>
                    </label>
                `;
            }).join('');

            packageGrid.innerHTML = cardsHtml;
            document.querySelectorAll('input[name="package"]').forEach((input) => {
                input.addEventListener('change', updateDigitalPanelUI);
            });
            updatePackageNote(selectedPackage);
        }

        function updatePackageNote(packageSlug) {
            const noteEl = document.querySelector('.package-note');
            if (!noteEl) return;
            noteEl.textContent = registerPackageNotes[packageSlug] || '';
        }

        function syncCurrentStepInput() {
            const stepInput = document.getElementById('current-step-input');
            if (stepInput) {
                stepInput.value = String(currentStep);
            }
        }

        function applyInitialStepState() {
            document.querySelectorAll('.form-step').forEach((stepEl) => stepEl.classList.remove('active'));
            document.querySelectorAll('.step-item').forEach((itemEl) => itemEl.classList.remove('active', 'completed'));

            for (let i = 1; i <= 2; i++) {
                const stepCard = document.getElementById(`step-id-${i}`);
                if (!stepCard) continue;
                if (i < currentStep) stepCard.classList.add('completed');
                if (i === currentStep) stepCard.classList.add('active');
            }

            const activeStep = document.getElementById(`step-${currentStep}`);
            if (activeStep) {
                activeStep.classList.add('active');
            }

            syncCurrentStepInput();
        }

        function buildRegisterPlatformOptions(selectedValue) {
            return SOCIAL_PLATFORM_OPTIONS.map((option) => {
                const selectedAttr = option.value === selectedValue ? 'selected' : '';
                return `<option value="${option.value}" ${selectedAttr}>${option.label}</option>`;
            }).join('');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function toggleRegisterCustomPlatformInput(row) {
            if (!row) return;
            const select = row.querySelector('select[name="social_platforms[]"]');
            const customInput = row.querySelector('input[name="social_platform_customs[]"]');
            if (!select || !customInput) return;
            const isCustom = select.value === '__custom__';
            customInput.style.display = isCustom ? 'block' : 'none';
            customInput.disabled = !isCustom;
            if (!isCustom) {
                customInput.value = '';
            }
        }

        function removeRegisterSocialRow(button) {
            const row = button.closest('.register-social-link-item');
            if (row) {
                row.remove();
            }
        }

        function addRegisterSocialRow(platform = 'instagram', url = '', customPlatform = '') {
            const container = document.getElementById('register-social-links-container');
            if (!container) return;

            const row = document.createElement('div');
            row.className = 'register-social-link-item';

            const isCustom = !SOCIAL_PLATFORM_OPTIONS.some((option) => option.value === platform);
            const selectedPlatform = isCustom ? '__custom__' : platform;
            const customValue = isCustom ? platform : customPlatform;
            const safeUrl = escapeHtml(url);
            const safeCustomValue = escapeHtml(customValue);

            row.innerHTML = `
                <div class="panel-social-row">
                    <select name="social_platforms[]" class="form-control">${buildRegisterPlatformOptions(selectedPlatform)}</select>
                    <input type="text" name="social_urls[]" class="form-control" placeholder="URL, kullanıcı adı veya numara" value="${safeUrl}">
                    <button type="button" class="panel-social-remove" aria-label="Satırı kaldır">Sil</button>
                </div>
                <input type="text" name="social_platform_customs[]" class="form-control platform-custom-input" placeholder="Özel platform adı (örn: patreon, substack, x)" value="${safeCustomValue}">
            `;

            container.appendChild(row);

            const select = row.querySelector('select[name="social_platforms[]"]');
            const removeButton = row.querySelector('.panel-social-remove');
            if (select) {
                select.addEventListener('change', () => toggleRegisterCustomPlatformInput(row));
            }
            if (removeButton) {
                removeButton.addEventListener('click', () => removeRegisterSocialRow(removeButton));
            }

            toggleRegisterCustomPlatformInput(row);
        }

        function syncPanelDisplayName() {
            const fullNameInput = document.querySelector('input[name="name"]');
            const panelNameInput = document.getElementById('panel-display-name');
            if (!fullNameInput || !panelNameInput) return;
            if (panelNameInput.value.trim() === '') {
                panelNameInput.value = fullNameInput.value.trim();
            }
        }

        function updateDigitalPanelUI() {
            const selectedPackage = getSelectedPackage();
            const isDigitalActive = digitalPackages.includes(selectedPackage);

            const badge = document.getElementById('panel-state-badge');
            const text = document.getElementById('panel-state-text');
            const disabledNote = document.getElementById('panel-disabled-note');
            const enabledFields = document.getElementById('panel-enabled-fields');
            const hiddenState = document.getElementById('digital-profile-enabled');

            if (!badge || !text || !disabledNote || !enabledFields || !hiddenState) return;

            hiddenState.value = isDigitalActive ? '1' : '0';

            badge.classList.remove('active', 'inactive');
            badge.classList.add(isDigitalActive ? 'active' : 'inactive');
            badge.textContent = isDigitalActive ? 'Aktif' : 'Pasif';

            if (selectedPackage === 'panel') {
                text.textContent = 'Sadece Panel paketinde dijital kartvizit siteniz aktif olur. Bu ayarlar profilinizi doğrudan yayına hazırlar.';
            } else if (selectedPackage === 'smart') {
                text.textContent = '1000 Baskı + 1 Yıllık Erişim paketinde dijital panel ve fiziksel baskı birlikte gelir. 1000 baskı dahildir ve 1 yıllık erişim tek ödeme ile tanımlanır.';
            } else {
                text.textContent = 'Klasik pakette dijital panel bulunmaz. Bu nedenle aşağıdaki dijital panel alanları pasif durumdadır.';
            }

            disabledNote.style.display = isDigitalActive ? 'none' : 'block';
            text.textContent = registerPackagePanelTexts[selectedPackage] || text.textContent;
            updatePackageNote(selectedPackage);
            enabledFields.style.display = isDigitalActive ? 'block' : 'none';

            enabledFields.querySelectorAll('input, textarea, select, button').forEach((field) => {
                field.disabled = !isDigitalActive;
            });
            enabledFields.querySelectorAll('.file-upload-box').forEach((box) => {
                box.classList.toggle('disabled', !isDigitalActive);
            });

            if (isDigitalActive) {
                syncPanelDisplayName();
            }
        }

        function nextStep(step) {
            // Basic validation for Step 2
            if (currentStep === 2) {
                const inputs = document.querySelectorAll('#step-2 input');
                let valid = true;
                inputs.forEach(input => {
                    if(!input.value) {
                        input.style.borderColor = 'red';
                        valid = false;
                    } else {
                        input.style.borderColor = '#e2e8f0';
                    }
                });
                if(!valid) return;
            }

            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-id-${currentStep}`).classList.add('completed');
            document.getElementById(`step-id-${currentStep}`).classList.remove('active');
            
            currentStep = step;
            
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step-id-${currentStep}`).classList.add('active');
            syncCurrentStepInput();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep(step) {
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-id-${currentStep}`).classList.remove('active');
            
            currentStep = step;
            
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step-id-${currentStep}`).classList.remove('completed');
            document.getElementById(`step-id-${currentStep}`).classList.add('active');
            syncCurrentStepInput();
        }

        function selectPackage(card) {
            document.querySelectorAll('.package-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            card.querySelector('input').checked = true;
            updatePackageNote(card.querySelector('input') ? card.querySelector('input').value : getSelectedPackage());
            updateDigitalPanelUI();
        }

        function normalizeHexColor(value) {
            const color = String(value || '').trim();
            return /^#[0-9a-fA-F]{6}$/.test(color) ? color.toUpperCase() : null;
        }

        function updateThemePreview(color) {
            const swatch = document.getElementById('theme-color-swatch');
            const code = document.getElementById('theme-color-code');
            const safeColor = normalizeHexColor(color) || '#0A2F2F';
            if (swatch) swatch.style.backgroundColor = safeColor;
            if (code) code.textContent = safeColor;
        }

        function ensureThemeSelectOption(color) {
            const select = document.getElementById('theme-color');
            if (!select) return;
            const safeColor = normalizeHexColor(color);
            if (!safeColor) return;

            const existing = Array.from(select.options).find((option) => option.value.toUpperCase() === safeColor);
            if (existing) {
                select.value = existing.value;
                return;
            }

            const oldCustomOption = select.querySelector('option[data-custom-color="1"]');
            if (oldCustomOption) {
                oldCustomOption.remove();
            }

            const customOption = document.createElement('option');
            customOption.value = safeColor;
            customOption.textContent = `Özel Renk (${safeColor})`;
            customOption.setAttribute('data-custom-color', '1');
            select.appendChild(customOption);
            select.value = safeColor;
        }

        function syncThemeColorFromSelect(selectElement) {
            const selected = normalizeHexColor(selectElement ? selectElement.value : '');
            const input = document.getElementById('theme-color-custom');
            const color = selected || '#0A2F2F';
            if (input) {
                input.value = color;
            }
            ensureThemeSelectOption(color);
            updateThemePreview(color);
        }

        function syncThemeColorFromText(inputElement, forceFallback = false) {
            const validColor = normalizeHexColor(inputElement ? inputElement.value : '');
            if (validColor) {
                if (inputElement) inputElement.value = validColor;
                ensureThemeSelectOption(validColor);
                updateThemePreview(validColor);
                return;
            }

            if (!forceFallback) return;

            const select = document.getElementById('theme-color');
            const fallback = normalizeHexColor(select ? select.value : '') || '#0A2F2F';
            if (inputElement) {
                inputElement.value = fallback;
            }
            ensureThemeSelectOption(fallback);
            updateThemePreview(fallback);
        }

        function triggerFileInput(fileInputId) {
            const input = document.getElementById(fileInputId);
            if (!input || input.disabled) return;
            input.click();
        }

        function setFileThumb(file, thumbId) {
            const thumb = document.getElementById(thumbId);
            if (!thumb) return;

            if (thumb.dataset.objectUrl) {
                URL.revokeObjectURL(thumb.dataset.objectUrl);
                delete thumb.dataset.objectUrl;
            }

            if (!file || !String(file.type || '').startsWith('image/')) {
                thumb.hidden = true;
                thumb.removeAttribute('src');
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            thumb.dataset.objectUrl = objectUrl;
            thumb.src = objectUrl;
            thumb.hidden = false;
        }

        function handleSelectedFile(inputId, labelId, previewBtnId, thumbId, defaultText) {
            const input = document.getElementById(inputId);
            const label = document.getElementById(labelId);
            const previewBtn = document.getElementById(previewBtnId);
            if (!input || !label) return;

            const selectedFile = input.files && input.files.length > 0 ? input.files[0] : null;
            label.textContent = selectedFile ? selectedFile.name : defaultText;
            if (previewBtn) {
                previewBtn.disabled = !selectedFile;
            }
            setFileThumb(selectedFile, thumbId);
        }

        function initDropzone(dropzoneId, inputId, labelId, previewBtnId, thumbId, defaultText) {
            const dropzone = document.getElementById(dropzoneId);
            const input = document.getElementById(inputId);
            if (!dropzone || !input) return;

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    if (input.disabled) return;
                    dropzone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('dragover');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                if (input.disabled) return;
                if (!event.dataTransfer || !event.dataTransfer.files || event.dataTransfer.files.length === 0) return;
                input.files = event.dataTransfer.files;
                handleSelectedFile(inputId, labelId, previewBtnId, thumbId, defaultText);
            });
        }

        const filePreviewModal = document.getElementById('file-preview-modal');
        const filePreviewImage = document.getElementById('file-preview-image');
        const filePreviewClose = document.getElementById('file-preview-close');

        function closeFilePreviewModal() {
            if (!filePreviewModal) return;
            filePreviewModal.classList.remove('open');
            filePreviewModal.setAttribute('aria-hidden', 'true');
            if (filePreviewImage) {
                filePreviewImage.removeAttribute('src');
            }
        }

        function previewSelectedImage(inputId) {
            const input = document.getElementById(inputId);
            if (!input || !input.files || input.files.length === 0) return;
            const file = input.files[0];
            if (!String(file.type || '').startsWith('image/')) return;

            if (!filePreviewModal || !filePreviewImage) return;

            const objectUrl = URL.createObjectURL(file);
            filePreviewImage.src = objectUrl;
            filePreviewModal.classList.add('open');
            filePreviewModal.setAttribute('aria-hidden', 'false');

            filePreviewImage.onload = () => {
                URL.revokeObjectURL(objectUrl);
            };
        }

        renderPackageCards();

        document.querySelectorAll('input[name="package"]').forEach((input) => {
            input.addEventListener('change', updateDigitalPanelUI);
        });

        const nameInput = document.querySelector('input[name="name"]');
        if (nameInput) {
            nameInput.addEventListener('blur', syncPanelDisplayName);
        }

        const addRegisterSocialBtn = document.getElementById('add-register-social-link');
        if (addRegisterSocialBtn) {
            addRegisterSocialBtn.addEventListener('click', () => addRegisterSocialRow());
        }

        if (oldRegisterSocialPlatforms.length > 0) {
            oldRegisterSocialPlatforms.forEach((platform, index) => {
                const url = typeof oldRegisterSocialUrls[index] === 'string' ? oldRegisterSocialUrls[index] : '';
                const customPlatform = typeof oldRegisterSocialCustoms[index] === 'string' ? oldRegisterSocialCustoms[index] : '';
                addRegisterSocialRow(String(platform || 'instagram'), url, customPlatform);
            });
        } else {
            addRegisterSocialRow('instagram');
        }

        const themeCustomInput = document.getElementById('theme-color-custom');
        const themeSelectInput = document.getElementById('theme-color');
        const initialTheme = normalizeHexColor(themeCustomInput ? themeCustomInput.value : '')
            || normalizeHexColor(themeSelectInput ? themeSelectInput.value : '')
            || '#0A2F2F';
        ensureThemeSelectOption(initialTheme);
        if (themeCustomInput) {
            themeCustomInput.value = initialTheme;
        }
        updateThemePreview(initialTheme);

        handleSelectedFile('logo-file', 'file-name', 'logo-preview-btn', 'logo-preview-thumb', 'Logo dosyasını sürükleyin veya seçin');
        handleSelectedFile('panel-photo-file', 'panel-photo-file-name', 'panel-photo-preview-btn', 'panel-photo-preview-thumb', 'Profil fotoğrafını sürükleyin veya seçin');
        initDropzone('logo-dropzone', 'logo-file', 'file-name', 'logo-preview-btn', 'logo-preview-thumb', 'Logo dosyasını sürükleyin veya seçin');
        initDropzone('panel-photo-dropzone', 'panel-photo-file', 'panel-photo-file-name', 'panel-photo-preview-btn', 'panel-photo-preview-thumb', 'Profil fotoğrafını sürükleyin veya seçin');

        const kvkkModal = document.getElementById('kvkk-modal');
        const kvkkOpen = document.getElementById('kvkk-open');
        const kvkkClose = document.getElementById('kvkk-close');

        function openKvkkModal(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (!kvkkModal) return;
            kvkkModal.classList.add('open');
            kvkkModal.setAttribute('aria-hidden', 'false');
        }

        function closeKvkkModal() {
            if (!kvkkModal) return;
            kvkkModal.classList.remove('open');
            kvkkModal.setAttribute('aria-hidden', 'true');
        }

        if (kvkkOpen) {
            kvkkOpen.addEventListener('click', openKvkkModal);
        }
        if (kvkkClose) {
            kvkkClose.addEventListener('click', closeKvkkModal);
        }
        if (kvkkModal) {
            kvkkModal.addEventListener('click', (event) => {
                if (event.target === kvkkModal) {
                    closeKvkkModal();
                }
            });
        }
        if (filePreviewClose) {
            filePreviewClose.addEventListener('click', closeFilePreviewModal);
        }
        if (filePreviewModal) {
            filePreviewModal.addEventListener('click', (event) => {
                if (event.target === filePreviewModal) {
                    closeFilePreviewModal();
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeKvkkModal();
                closeFilePreviewModal();
            }
        });

        applyInitialStepState();
        updateDigitalPanelUI();
    </script>
    
</body>
</html>
