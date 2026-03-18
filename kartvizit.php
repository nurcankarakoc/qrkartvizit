<?php
require_once __DIR__ . '/core/db.php';

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

function render_state_page(int $status_code, string $title, string $message): void
{
    http_response_code($status_code);
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | QR Kartvizit</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: linear-gradient(135deg, #0A2F2F 0%, #072424 50%, #0f1c1c 100%);
                font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
                color: #fff;
                padding: 2rem;
            }
            .box {
                width: min(480px, 100%);
                padding: 3rem 2.5rem;
                background: rgba(255,255,255,0.05);
                border: 1px solid rgba(166, 128, 63, 0.3);
                border-radius: 24px;
                backdrop-filter: blur(20px);
                text-align: center;
            }
            .icon { font-size: 3rem; margin-bottom: 1.5rem; }
            h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.75rem; color: #fff; }
            p { color: rgba(255,255,255,0.6); line-height: 1.7; }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="icon">🔒</div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || strlen($slug) > 255) {
    render_state_page(404, 'Profil Bulunamadi', 'Gecerli bir dijital kartvizit baglantisi kullanin.');
}

$has_user_active_column = table_has_column($pdo, 'users', 'is_active');
$user_active_select = $has_user_active_column ? ', u.is_active AS user_active' : '';

$stmt = $pdo->prepare(
    "SELECT p.*, u.name AS owner_name{$user_active_select}
     FROM profiles p
     JOIN users u ON u.id = p.user_id
     WHERE p.slug = ?
     LIMIT 1"
);
$stmt->execute([$slug]);
$profile = $stmt->fetch();

if (!$profile) {
    render_state_page(404, 'Profil Bulunamadi', 'Aradiginiz kartvizit sayfasi mevcut degil.');
}

$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([(int)$profile['user_id']]);
$order = $stmt->fetch();
$has_digital_package = has_digital_profile_package((string)($order['package'] ?? ''));

if (!$has_digital_package) {
    render_state_page(403, 'Profil Aktif Degil', 'Bu hesapta dijital profil paketi aktif degil.');
}

$profile_is_active = !table_has_column($pdo, 'profiles', 'is_active') || (int)($profile['is_active'] ?? 1) === 1;
$user_is_active = !$has_user_active_column || (int)($profile['user_active'] ?? 1) === 1;

$is_expired = false;
if (table_has_column($pdo, 'profiles', 'expiry_date') && !empty($profile['expiry_date'])) {
    $is_expired = strtotime((string)$profile['expiry_date'] . ' 23:59:59') < time();
}

if (!$profile_is_active || !$user_is_active || $is_expired) {
    render_state_page(410, 'Profil Yayin Disi', 'Bu dijital kartvizit su an goruntulenemiyor.');
}

if (table_has_column($pdo, 'profiles', 'view_count')) {
    $view_stmt = $pdo->prepare("UPDATE profiles SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
    $view_stmt->execute([(int)$profile['id']]);
}

$stmt = $pdo->prepare("SELECT platform, url FROM social_links WHERE profile_id = ? ORDER BY id ASC");
$stmt->execute([(int)$profile['id']]);
$social_links = $stmt->fetchAll();

$full_name = trim((string)($profile['full_name'] ?? ''));
if ($full_name === '') {
    $full_name = trim((string)($profile['owner_name'] ?? 'Dijital Kartvizit'));
}

$title_text   = trim((string)($profile['title'] ?? ''));
$company      = trim((string)($profile['company'] ?? ''));
$bio          = trim((string)($profile['bio'] ?? ''));
$phone_work   = trim((string)($profile['phone_work'] ?? ''));
$email_work   = trim((string)($profile['email_work'] ?? ''));
$photo_path   = trim((string)($profile['photo_path'] ?? ''));

$initial    = strtoupper(substr($full_name !== '' ? $full_name : 'U', 0, 1));
$phone_href = $phone_work !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone_work) : '';
$email_href = $email_work !== '' ? 'mailto:' . $email_work : '';

$platform_meta = [
    'instagram' => ['label' => 'Instagram',  'icon' => 'instagram',      'color' => '#E1306C'],
    'whatsapp'  => ['label' => 'WhatsApp',   'icon' => 'message-circle', 'color' => '#25D366'],
    'linkedin'  => ['label' => 'LinkedIn',   'icon' => 'linkedin',       'color' => '#0A66C2'],
    'website'   => ['label' => 'Web Sitesi', 'icon' => 'globe',          'color' => '#A6803F'],
    'twitter'   => ['label' => 'Twitter/X',  'icon' => 'twitter',        'color' => '#1DA1F2'],
    'youtube'   => ['label' => 'YouTube',    'icon' => 'youtube',        'color' => '#FF0000'],
    'github'    => ['label' => 'GitHub',     'icon' => 'github',         'color' => '#333'],
    'mail'      => ['label' => 'E-Posta',    'icon' => 'mail',           'color' => '#A6803F'],
];

$name_parts = explode(' ', $full_name);
$first_name = $name_parts[0] ?? $full_name;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?> | Dijital Kartvizit</title>
    <meta name="description" content="<?php echo htmlspecialchars($full_name); ?><?php if($title_text): ?> — <?php echo htmlspecialchars($title_text); ?><?php endif; ?><?php if($company): ?> | <?php echo htmlspecialchars($company); ?><?php endif; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($full_name); ?> | Dijital Kartvizit">
    <meta property="og:type" content="profile">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* =========================================================
           DESIGN TOKENS
        ========================================================= */
        :root {
            --brand-navy:    #0A2F2F;
            --brand-navy-dk: #072424;
            --brand-navy-md: #0e3d3d;
            --brand-gold:    #A6803F;
            --brand-gold-lt: #C5A059;
            --brand-gold-xl: #D4B06A;
            --white:         #ffffff;
            --bg-page:       #f0f4f4;
            --card-bg:       #ffffff;
            --text-dk:       #0f1a1a;
            --text-md:       #2d4a4a;
            --text-lt:       #5a7a7a;
            --border:        rgba(10, 47, 47, 0.1);
            --shadow-card:   0 32px 80px rgba(10,47,47,0.14), 0 4px 16px rgba(10,47,47,0.06);
            --shadow-btn:    0 8px 24px rgba(10,47,47,0.2);
            --radius-card:   28px;
            --radius-btn:    16px;
        }

        /* =========================================================
           RESET & BASE
        ========================================================= */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-page);
            color: var(--text-dk);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* =========================================================
           ANIMATED BACKGROUND
        ========================================================= */
        .bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .bg-canvas::before {
            content: '';
            position: absolute;
            width: 700px; height: 700px;
            top: -200px; left: -150px;
            background: radial-gradient(circle, rgba(10,47,47,0.12) 0%, transparent 70%);
            border-radius: 50%;
            animation: driftA 18s ease-in-out infinite alternate;
        }

        .bg-canvas::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            bottom: -100px; right: -100px;
            background: radial-gradient(circle, rgba(166,128,63,0.14) 0%, transparent 70%);
            border-radius: 50%;
            animation: driftB 14s ease-in-out infinite alternate;
        }

        @keyframes driftA {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(60px, 80px) scale(1.1); }
        }
        @keyframes driftB {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(-50px, -60px) scale(1.15); }
        }

        /* =========================================================
           LAYOUT
        ========================================================= */
        .page-wrap {
            position: relative;
            z-index: 1;
            max-width: 480px;
            margin: 0 auto;
            padding: 2rem 1rem 4rem;
        }

        /* =========================================================
           HEADER BAND (Powered by)
        ========================================================= */
        .powered-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            opacity: 0;
            animation: fadeSlideDown 0.6s ease 0.1s forwards;
        }

        .powered-bar .dot {
            width: 8px; height: 8px;
            background: var(--brand-gold);
            border-radius: 50%;
            display: inline-block;
        }

        .powered-bar span {
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-lt);
        }

        /* =========================================================
           MAIN CARD
        ========================================================= */
        .kv-card {
            background: var(--card-bg);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(10,47,47,0.07);
            overflow: hidden;
            opacity: 0;
            animation: fadeSlideUp 0.7s ease 0.2s forwards;
        }

        /* =========================================================
           CARD HERO (Top gradient section)
        ========================================================= */
        .card-hero {
            background: linear-gradient(145deg, var(--brand-navy) 0%, var(--brand-navy-md) 60%, #1a4a4a 100%);
            padding: 2.5rem 2rem 4rem;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .card-hero::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            top: -80px; right: -80px;
            background: radial-gradient(circle, rgba(166,128,63,0.18) 0%, transparent 60%);
            border-radius: 50%;
        }

        .card-hero::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            bottom: 20px; left: -60px;
            background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 60%);
            border-radius: 50%;
        }

        /* Pattern overlay */
        .card-hero .hero-pattern {
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(166,128,63,0.08) 0%, transparent 40%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.04) 0%, transparent 40%);
        }

        /* =========================================================
           AVATAR
        ========================================================= */
        .avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 1.25rem;
            z-index: 1;
        }

        .avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 4px solid rgba(166, 128, 63, 0.6);
            box-shadow: 0 0 0 8px rgba(166,128,63,0.12), 0 16px 40px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, var(--brand-navy-md), #1a5050);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 900;
            overflow: hidden;
            animation: avatarPop 0.6s cubic-bezier(0.34,1.56,0.64,1) 0.4s both;
        }

        @keyframes avatarPop {
            from { transform: scale(0.6); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        .avatar img {
            width: 100%; height: 100%;
            object-fit: cover;
        }

        .avatar-ring {
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px solid rgba(166,128,63,0.25);
            animation: ringPulse 3s ease-in-out infinite;
        }

        @keyframes ringPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50%       { transform: scale(1.08); opacity: 0; }
        }

        /* =========================================================
           NAME & TITLE
        ========================================================= */
        .hero-name {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.2;
            z-index: 1;
            position: relative;
            margin-bottom: 0.4rem;
        }

        .hero-subtitle {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.65);
            font-weight: 500;
            z-index: 1;
            position: relative;
        }

        .subtitle-sep {
            color: var(--brand-gold-lt);
            margin: 0 0.4rem;
        }

        .hero-company-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.75rem;
            background: rgba(166,128,63,0.18);
            border: 1px solid rgba(166,128,63,0.35);
            color: var(--brand-gold-xl);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 0.35rem 0.85rem;
            border-radius: 30px;
            z-index: 1;
            position: relative;
            backdrop-filter: blur(8px);
        }

        /* =========================================================
           CARD BODY
        ========================================================= */
        .card-body {
            padding: 0 1.5rem 2rem;
            position: relative;
            margin-top: -2rem;
        }

        /* =========================================================
           BIO SECTION
        ========================================================= */
        .bio-section {
            background: #f8fafa;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .bio-section p {
            color: var(--text-md);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        /* =========================================================
           ACTION BUTTONS
        ========================================================= */
        .actions {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            text-decoration: none;
            padding: 0.95rem 1.25rem;
            border-radius: var(--radius-btn);
            font-weight: 700;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border: none;
            letter-spacing: 0.2px;
        }

        .btn:active { transform: scale(0.97) !important; }

        .btn i { width: 18px; height: 18px; flex-shrink: 0; }

        .btn-call {
            background: linear-gradient(135deg, var(--brand-navy) 0%, var(--brand-navy-md) 100%);
            color: #fff;
            box-shadow: 0 8px 24px rgba(10,47,47,0.3);
        }

        .btn-call:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(10,47,47,0.4);
        }

        .btn-email {
            background: #fff;
            color: var(--text-dk);
            border: 1.5px solid var(--border);
            box-shadow: 0 4px 12px rgba(10,47,47,0.06);
        }

        .btn-email:hover {
            transform: translateY(-2px);
            border-color: rgba(10,47,47,0.2);
            box-shadow: 0 8px 20px rgba(10,47,47,0.1);
        }

        .btn-save-contact {
            background: linear-gradient(135deg, var(--brand-gold) 0%, var(--brand-gold-lt) 100%);
            color: #fff;
            box-shadow: 0 8px 24px rgba(166,128,63,0.3);
            font-size: 0.88rem;
        }

        .btn-save-contact:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(166,128,63,0.4);
        }

        /* =========================================================
           DIVIDER
        ========================================================= */
        .section-divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1.25rem 0 1rem;
        }

        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .section-divider span {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-lt);
            white-space: nowrap;
        }

        /* =========================================================
           SOCIAL LINKS
        ========================================================= */
        .links-grid {
            display: grid;
            gap: 0.6rem;
        }

        .social-link {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            text-decoration: none;
            color: var(--text-dk);
            transition: all 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .social-link::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: var(--platform-color, var(--brand-gold));
            border-radius: 0 3px 3px 0;
            transform: scaleY(0);
            transition: transform 0.22s ease;
        }

        .social-link:hover {
            transform: translateX(4px);
            border-color: rgba(10,47,47,0.15);
            box-shadow: 0 4px 16px rgba(10,47,47,0.08);
        }

        .social-link:hover::before {
            transform: scaleY(1);
        }

        .social-icon-wrap {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: rgba(166,128,63,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.22s;
        }

        .social-link:hover .social-icon-wrap {
            background: rgba(10,47,47,0.08);
        }

        .social-icon-wrap i {
            width: 16px; height: 16px;
            color: var(--platform-color, var(--brand-gold));
        }

        .social-link-info {
            flex: 1;
            min-width: 0;
        }

        .social-link-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-dk);
            margin-bottom: 0.1rem;
        }

        .social-link-url {
            display: block;
            font-size: 0.75rem;
            color: var(--text-lt);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .social-link-arrow i {
            width: 14px; height: 14px;
            color: var(--text-lt);
            transition: transform 0.2s;
        }

        .social-link:hover .social-link-arrow i {
            transform: translateX(3px);
            color: var(--brand-navy);
        }

        /* =========================================================
           FOOTER / BRAND
        ========================================================= */
        .kv-footer {
            text-align: center;
            margin-top: 1.25rem;
            padding: 1rem;
            opacity: 0;
            animation: fadeSlideUp 0.6s ease 0.8s forwards;
        }

        .kv-footer a {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-lt);
            text-decoration: none;
            transition: color 0.2s;
        }

        .kv-footer a:hover { color: var(--brand-navy); }

        .kv-footer .brand-dot {
            width: 6px; height: 6px;
            background: var(--brand-gold);
            border-radius: 50%;
        }

        /* =========================================================
           ANIMATIONS
        ========================================================= */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .link-item {
            opacity: 0;
            animation: fadeSlideUp 0.4s ease forwards;
        }

        /* =========================================================
           RESPONSIVE TWEAKS
        ========================================================= */
        @media (max-width: 480px) {
            .page-wrap { padding: 1.25rem 0.75rem 3rem; }
            .card-hero { padding: 2rem 1.5rem 3.5rem; }
            .card-body { padding: 0 1.25rem 1.75rem; }
            .avatar { width: 96px; height: 96px; font-size: 2.1rem; }
            .hero-name { font-size: 1.5rem; }
        }

        @media (min-width: 481px) {
            .actions { grid-template-columns: 1fr 1fr; }
            .btn-save-contact { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
<div class="bg-canvas"></div>

<div class="page-wrap">

    <!-- Powered By Bar -->
    <div class="powered-bar">
        <span class="dot"></span>
        <span>Zerosoft Dijital Kartvizit</span>
        <span class="dot"></span>
    </div>

    <!-- Main Card -->
    <div class="kv-card">

        <!-- Card Hero -->
        <div class="card-hero">
            <div class="hero-pattern"></div>

            <!-- Avatar -->
            <div class="avatar-wrap">
                <div class="avatar-ring"></div>
                <div class="avatar">
                    <?php if ($photo_path !== ''): ?>
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initial); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Name -->
            <h1 class="hero-name"><?php echo htmlspecialchars($full_name); ?></h1>

            <!-- Title & Company -->
            <?php if ($title_text !== '' || $company !== ''): ?>
                <p class="hero-subtitle">
                    <?php if ($title_text !== ''): ?>
                        <?php echo htmlspecialchars($title_text); ?>
                    <?php endif; ?>
                    <?php if ($title_text !== '' && $company !== ''): ?>
                        <span class="subtitle-sep">·</span>
                    <?php endif; ?>
                    <?php if ($company !== ''): ?>
                        <?php echo htmlspecialchars($company); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Card Body -->
        <div class="card-body">

            <!-- Bio -->
            <?php if ($bio !== ''): ?>
                <div class="bio-section">
                    <p><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="actions">
                <?php if ($phone_href !== ''): ?>
                    <a class="btn btn-call" href="<?php echo htmlspecialchars($phone_href); ?>" id="btn-call">
                        <i data-lucide="phone"></i>
                        Hemen Ara
                    </a>
                <?php endif; ?>

                <?php if ($email_href !== ''): ?>
                    <a class="btn btn-email" href="<?php echo htmlspecialchars($email_href); ?>" id="btn-email">
                        <i data-lucide="mail"></i>
                        E-posta Gönder
                    </a>
                <?php endif; ?>

                <!-- Save Contact Button -->
                <?php if ($phone_work !== '' || $email_work !== ''): ?>
                    <a class="btn btn-save-contact" href="javascript:void(0)" id="btn-save-contact" onclick="downloadVCard()">
                        <i data-lucide="contact"></i>
                        Rehbere Kaydet
                    </a>
                <?php endif; ?>
            </div>

            <!-- Social Links -->
            <?php if (!empty($social_links)): ?>
                <div class="section-divider">
                    <span>Bağlantılar</span>
                </div>

                <div class="links-grid">
                    <?php foreach ($social_links as $idx => $link): ?>
                        <?php
                        $platform = strtolower((string)($link['platform'] ?? ''));
                        $url      = (string)($link['url'] ?? '');
                        if ($url === '') continue;
                        $meta         = $platform_meta[$platform] ?? ['label' => ucfirst($platform), 'icon' => 'link-2', 'color' => '#A6803F'];
                        $delay_ms     = 500 + ($idx * 80);
                        $display_url  = preg_replace('#^https?://(www\.)?#', '', $url);
                        $display_url  = rtrim($display_url, '/');
                        ?>
                        <a class="social-link link-item"
                           href="<?php echo htmlspecialchars($url); ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           style="--platform-color: <?php echo htmlspecialchars($meta['color']); ?>; animation-delay: <?php echo $delay_ms; ?>ms;"
                           id="social-<?php echo htmlspecialchars($platform); ?>">
                            <div class="social-icon-wrap">
                                <i data-lucide="<?php echo htmlspecialchars($meta['icon']); ?>"></i>
                            </div>
                            <div class="social-link-info">
                                <span class="social-link-label"><?php echo htmlspecialchars($meta['label']); ?></span>
                                <span class="social-link-url"><?php echo htmlspecialchars($display_url); ?></span>
                            </div>
                            <div class="social-link-arrow">
                                <i data-lucide="arrow-right"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div><!-- /card-body -->
    </div><!-- /kv-card -->

    <!-- Footer -->
    <div class="kv-footer">
        <a href="/" id="footer-brand-link">
            <span class="brand-dot"></span>
            Zerosoft QR Kartvizit ile oluşturuldu
            <span class="brand-dot"></span>
        </a>
    </div>

</div><!-- /page-wrap -->

<script>
    lucide.createIcons();

    // vCard download
    function downloadVCard() {
        const name    = <?php echo json_encode($full_name); ?>;
        const phone   = <?php echo json_encode($phone_work); ?>;
        const email   = <?php echo json_encode($email_work); ?>;
        const title   = <?php echo json_encode($title_text); ?>;
        const company = <?php echo json_encode($company); ?>;

        const lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' + name,
            'N:' + name.split(' ').reverse().join(';') + ';;;',
        ];
        if (title)   lines.push('TITLE:' + title);
        if (company) lines.push('ORG:' + company);
        if (phone)   lines.push('TEL;TYPE=WORK,VOICE:' + phone);
        if (email)   lines.push('EMAIL;TYPE=WORK:' + email);
        lines.push('END:VCARD');

        const blob = new Blob([lines.join('\r\n')], { type: 'text/vcard' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = name.replace(/\s+/g, '_') + '.vcf';
        a.click();
        URL.revokeObjectURL(url);
    }
</script>
</body>
</html>
