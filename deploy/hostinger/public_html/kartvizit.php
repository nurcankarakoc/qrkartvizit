<?php
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/subscription.php';

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_e  = str_replace('`', '``', $table);
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
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Inter,sans-serif;padding:2rem;}
        .box{width:min(420px,100%);padding:3rem 2rem;text-align:center;background:#fff;border-radius:32px;box-shadow:0 15px 45px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03)}
        .icon{font-size:3rem;margin-bottom:1.5rem}
        h1{font-size:1.4rem;font-weight:800;color:#0A2F2F;margin-bottom:1rem;letter-spacing:-0.5px}
        p{color:#64748b;line-height:1.7;font-size:.95rem}
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">✨</div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo htmlspecialchars($msg); ?></p>
    </div>
</body></html><?php
    exit();
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' || strlen($slug) > 255) render_state_page(404, 'Profil Bulunamadı', 'Geçerli bir dijital kartvizit bağlantısı kullanın.');

$has_user_active  = table_has_column($pdo, 'users', 'is_active');
$user_active_sel  = $has_user_active ? ', u.is_active AS user_active' : '';

$stmt = $pdo->prepare(
    "SELECT p.*, u.name AS owner_name{$user_active_sel}
     FROM profiles p JOIN users u ON u.id = p.user_id
     WHERE p.slug = ? LIMIT 1"
);
$stmt->execute([$slug]);
$profile = $stmt->fetch();
if (!$profile) render_state_page(404, 'Profil Bulunamadı', 'Aradığınız kartvizit sayfası mevcut değil.');

$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([(int)$profile['user_id']]);
$order = $stmt->fetch();
if (!qrk_user_has_digital_access($pdo, (int)$profile['user_id'], (string)($order['package'] ?? ''))) {
    render_state_page(403, 'Profil Aktif Değil', 'Bu hesapta dijital profil paketi aktif değil.');
}

$profile_active = !table_has_column($pdo,'profiles','is_active') || (int)($profile['is_active']??1)===1;
$user_active    = !$has_user_active || (int)($profile['user_active']??1)===1;
if (!$profile_active || !$user_active) render_state_page(410, 'Profil Yayın Dışı', 'Bu dijital kartvizit şu an görüntülenemiyor.');

$stmt = $pdo->prepare("SELECT platform,url FROM social_links WHERE profile_id=? ORDER BY id ASC");
$stmt->execute([(int)$profile['id']]);
$social_links = $stmt->fetchAll();

$full_name   = trim((string)($profile['full_name'] ?? '')) ?: trim((string)($profile['owner_name'] ?? 'Dijital Kartvizit'));
$title_text  = trim((string)($profile['title']      ?? ''));
$company     = trim((string)($profile['company']    ?? ''));
$bio         = trim((string)($profile['bio']        ?? ''));
$phone_work  = trim((string)($profile['phone_work'] ?? ''));
$email_work  = trim((string)($profile['email_work'] ?? ''));
$photo_path  = trim((string)($profile['photo_path'] ?? ''));
$brand_color = normalize_hex_color((string)($profile['brand_color'] ?? '')) ?? '#0A2F2F';
$cover_path  = trim((string)($profile['cover_photo'] ?? ''));
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
    'instagram' => ['icon'=>'instagram'],
    'whatsapp'  => ['icon'=>'message-circle'],
    'linkedin'  => ['icon'=>'linkedin'],
    'website'   => ['icon'=>'globe'],
    'x'         => ['icon'=>'twitter'],
    'twitter'   => ['icon'=>'twitter'],
    'youtube'   => ['icon'=>'youtube'],
    'github'    => ['icon'=>'github'],
    'facebook'  => ['icon'=>'facebook'],
    'tiktok'    => ['icon'=>'music'],
    'mail'      => ['icon'=>'mail'],
    'phone'     => ['icon'=>'phone'],
    'maps'      => ['icon'=>'map-pin'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { 
            --brand: <?php echo $brand_color; ?>; 
            --accent: <?php echo ($brand_color === '#0A2F2F' || $brand_color === '') ? '#A6803F' : $brand_color; ?>;
            --accent-light: color-mix(in srgb, var(--accent) 80%, white);
            --brand-dark: color-mix(in srgb, var(--brand) 80%, black);
            --bg: #f8fafc; 
            --ink: #0f172a; 
            --ink-lt: #475569; 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f1f5f9 0%, #ffffff 100%); 
            color: var(--ink); 
            line-height: 1.5; 
            min-height: 100vh;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 2rem; 
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient glow behind the card for atmosphere */
        .ambient-glow {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 120vw; height: 120vh;
            background: radial-gradient(circle at 20% 30%, color-mix(in srgb, var(--accent) 15%, transparent) 0%, transparent 40%),
                        radial-gradient(circle at 80% 70%, color-mix(in srgb, var(--brand) 12%, transparent) 0%, transparent 50%);
            z-index: 1; pointer-events: none;
        }

        /* Main Container: Premium Glass/Soft UI */
        .card-wrapper {
            width: 100%;
            max-width: 1000px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 40px;
            box-shadow: 0 50px 100px rgba(10, 47, 47, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.9);
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            overflow: hidden;
            position: relative;
            z-index: 10;
        }

        /* ── LEFT PANE (HEADER & PROFILE) ──────────────────── */
        .profile-pane {
            position: relative;
            padding: 5rem 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: <?php echo $cover_path ? "linear-gradient(to bottom, rgba(10,47,47,0.4), rgba(10,47,47,0.95)), url('".htmlspecialchars($cover_path)."')" : "linear-gradient(145deg, ".htmlspecialchars($cover_dark).", ".htmlspecialchars($cover_color).")"; ?>;
            background-size: cover;
            background-position: center;
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.1);
        }

        .avatar {
            width: 160px; height: 160px; border-radius: 50%;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 4.5rem; font-weight: 900; color: var(--accent);
            border: 4px solid var(--accent);
            box-shadow: 0 0 0 8px rgba(255,255,255,0.1), 0 25px 50px rgba(0,0,0,0.4);
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 2;
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .avatar.avatar--monogram {
            background: var(--avatar-bg, var(--accent));
            color: var(--avatar-fg, #fff);
            border-color: rgba(255,255,255,0.92);
            font-size: 3.6rem;
            letter-spacing: -1px;
        }
        .avatar:hover { transform: scale(1.05) translateY(-5px); box-shadow: 0 0 0 12px rgba(255,255,255,0.15), 0 35px 60px rgba(0,0,0,0.5); }
        .avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

        .name { font-size: 2.4rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 0.5rem; text-shadow: 0 4px 15px rgba(0,0,0,0.4); line-height: 1.1; }
        .sub { font-size: 1.15rem; font-weight: 500; opacity: 0.95; display: flex; align-items: center; gap: 0.75rem; justify-content: center; margin-bottom: 1.5rem; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .sub span { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; box-shadow: 0 0 12px var(--accent); }

        .bio-text { font-size: 1rem; font-weight: 400; line-height: 1.7; opacity: 0.85; max-width: 95%; margin-top: auto; }

        /* ── RIGHT PANE (ACTIONS & LINKS) ──────────────────── */
        .content-pane {
            padding: 4rem 3.5rem;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .content-pane::-webkit-scrollbar { width: 6px; }
        .content-pane::-webkit-scrollbar-track { background: transparent; }
        .content-pane::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        
        .btn-action {
            display: flex; align-items: center; justify-content: center; gap: 0.75rem;
            padding: 1.25rem; border-radius: 20px; text-decoration: none;
            font-weight: 800; font-size: 0.95rem; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; overflow: hidden;
            z-index: 1;
        }
        
        .btn-call {
            grid-column: span 2;
            background: linear-gradient(135deg, var(--accent-light), var(--accent));
            color: #fff;
            border: none;
            box-shadow: inset 0 2px 0 rgba(255,255,255,0.3), 0 15px 35px color-mix(in srgb, var(--accent) 30%, transparent);
        }
        .btn-call:hover { transform: translateY(-4px); box-shadow: inset 0 2px 0 rgba(255,255,255,0.4), 0 20px 45px color-mix(in srgb, var(--accent) 45%, transparent); }
        
        .btn-secondary {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            color: var(--ink);
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: inset 0 2px 0 #fff, 0 8px 20px rgba(0,0,0,0.03);
        }
        .btn-secondary:hover {
            transform: translateY(-4px); 
            box-shadow: inset 0 2px 0 #fff, 0 15px 30px rgba(10,47,47,0.08);
            border-color: color-mix(in srgb, var(--accent) 30%, transparent);
            color: var(--accent);
        }
        .btn-secondary i { transition: 0.3s; color: var(--ink); }
        .btn-secondary:hover i { color: var(--accent); }

        /* Links List */
        .links-container { display: flex; flex-direction: column; gap: 1.25rem; }
        
        .link-card {
            display: flex; align-items: center; gap: 1.25rem;
            padding: 1rem 1.25rem; text-decoration: none; color: var(--ink);
            background: linear-gradient(to right, #ffffff, #fcfdfe);
            border: 1px solid rgba(0,0,0,0.04);
            border-left: 4px solid var(--accent); /* Premium thick accent edge */
            border-radius: 20px; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative; z-index: 1;
            box-shadow: inset 0 2px 0 #fff, 0 8px 20px rgba(0,0,0,0.02);
        }
        
        .link-card:hover { 
            transform: translateX(8px); 
            background: #fff; 
            border-color: color-mix(in srgb, var(--accent) 20%, transparent);
            box-shadow: inset 0 2px 0 #fff, 0 15px 35px color-mix(in srgb, var(--accent) 15%, transparent); 
        }
        
        .link-icon-box {
            width: 52px; height: 52px; background: color-mix(in srgb, var(--accent) 10%, #fff); border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            color: var(--accent); transition: 0.4s;
            border: 1px solid color-mix(in srgb, var(--accent) 20%, transparent);
        }
        .link-card:hover .link-icon-box { background: linear-gradient(135deg, var(--accent-light), var(--accent)); color: #fff; transform: scale(1.1) rotate(-5deg); border-color: transparent; box-shadow: 0 8px 20px color-mix(in srgb, var(--accent) 35%, transparent); }
        
        .link-label { font-weight: 800; font-size: 1.05rem; flex: 1; letter-spacing: -0.2px; }
        
        .link-arrow {
            width: 36px; height: 36px; background: #f8fafc; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: #94a3b8;
            transition: 0.4s; border: 1px solid #eef2f6;
        }
        .link-card:hover .link-arrow { background: color-mix(in srgb, var(--accent) 10%, transparent); color: var(--accent); transform: rotate(45deg); border-color: transparent; }

        .footer-branding { margin-top: auto; padding-top: 1rem; text-align: center; }
        .footer-branding span { font-size: 0.75rem; font-weight: 900; letter-spacing: 3px; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-transform: uppercase; }

        @media (max-width: 900px) {
            .card-wrapper { grid-template-columns: 1fr; max-width: 480px; border-radius: 40px; }
            .profile-pane { padding: 4rem 2.5rem; border-bottom-left-radius: 0; border-bottom-right-radius: 0; border-right: none; }
            .content-pane { padding: 3rem 2.5rem; overflow-y: visible; max-height: none; }
            body { padding: 1.5rem; height: auto; min-height: 100vh; display: block; }
            .card-wrapper { margin: 0 auto; margin-bottom: 2rem; }
        }
        @media (max-width: 480px) {
            body { padding: 0; background: #fff; display: block; }
            .ambient-glow { display: none; }
            .card-wrapper { border-radius: 0; max-width: 100%; border: none; box-shadow: none; margin: 0; }
            .content-pane { padding: 2.5rem 1.5rem; background: #fff; }
            .profile-pane { padding: 4.5rem 1.5rem 3.5rem; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; box-shadow: 0 15px 40px rgba(0,0,0,0.1); z-index: 10; }
            .avatar { width: 140px; height: 140px; font-size: 3.5rem; }
            .name { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="card-wrapper">
        <div class="profile-pane">
            <div class="avatar<?php echo $photo_path ? '' : ' avatar--monogram'; ?>" <?php if (!$photo_path): ?>style="--avatar-bg: <?php echo htmlspecialchars($avatar_color); ?>; --avatar-fg: <?php echo htmlspecialchars($avatar_text_color); ?>;"<?php endif; ?>>
                <?php if ($photo_path): ?><img src="<?php echo htmlspecialchars($photo_path); ?>"><?php else: echo htmlspecialchars($avatar_initial); endif; ?>
            </div>
            <h1 class="name"><?php echo htmlspecialchars($full_name); ?></h1>
            <div class="sub">
                <?php if($title_text): echo htmlspecialchars($title_text); endif; ?>
                <?php if($title_text && $company): ?><span></span><?php endif; ?>
                <?php if($company): echo htmlspecialchars($company); endif; ?>
            </div>
            <?php if ($bio): ?>
                <p class="bio-text"><?php echo htmlspecialchars($bio); ?></p>
            <?php endif; ?>
        </div>

        <div class="content-pane">
            <div class="actions-grid">
                <?php if($phone_work): ?>
                    <a href="tel:<?php echo preg_replace('/[^0-9+]/','', $phone_work); ?>" class="btn-action btn-call">
                        <i data-lucide="phone" style="width: 22px;"></i> Hemen Ara
                    </a>
                <?php endif; ?>
                
                <a href="processes/generate_vcard.php?slug=<?php echo urlencode($slug); ?>" class="btn-action btn-secondary" <?php if(!$phone_work) echo 'style="grid-column: span 2;"'; ?>>
                    <i data-lucide="user-plus" style="width: 20px;"></i> Rehbere Kaydet
                </a>
                
                <?php if($email_work): ?>
                    <a href="mailto:<?php echo htmlspecialchars($email_work); ?>" class="btn-action btn-secondary" <?php if(!$phone_work) echo 'style="grid-column: span 2;"'; ?>>
                        <i data-lucide="mail" style="width: 20px;"></i> E-posta Gönder
                    </a>
                <?php endif; ?>
            </div>

            <?php if(!empty($social_links)): ?>
                <div class="links-container">
                    <?php foreach($social_links as $link): 
                        $p = strtolower($link['platform']);
                        $meta = $platform_meta[$p] ?? ['icon'=>'link-2'];
                    ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="link-card">
                            <div class="link-icon-box"><i data-lucide="<?php echo $meta['icon']; ?>" style="width:22px;"></i></div>
                            <span class="link-label"><?php echo ucwords($p); ?></span>
                            <div class="link-arrow">
                                <i data-lucide="arrow-right" style="width: 18px;"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="footer-branding">
                <span><i data-lucide="zap" style="width: 16px; color: var(--gold);"></i> ZEROSOFT</span>
            </div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
