<?php
require_once __DIR__ . '/core/db.php';

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_e  = str_replace('`', '``', $table);
    $column_e = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_e}` LIKE '{$column_e}'");
    return (bool)$stmt->fetch();
}

function has_digital_profile_package(?string $package): bool
{
    if (!is_string($package) || $package === '') return false;
    $n = strtolower(trim($package));
    return str_contains($n, 'panel') || str_contains($n, 'smart') || str_contains($n, 'akilli');
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
if (!has_digital_profile_package((string)($order['package'] ?? ''))) {
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
$brand_color = trim((string)($profile['brand_color'] ?? '#0A2F2F'));
$cover_path  = trim((string)($profile['cover_photo'] ?? ''));
$initial     = strtoupper(substr($full_name,0,1));

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --brand: <?php echo $brand_color; ?>; --bg: #ffffff; --ink: #1e293b; --ink-lt: #f1f5f9; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #fcfdfe; color: var(--ink); line-height: 1.5; }
        
        .card { max-width: 420px; margin: 0 auto; min-height: 100vh; background: var(--bg); display: flex; flex-direction: column; overflow: hidden; }
        
        /* ── HEADER ────────────────────────────────────────── */
        .header { 
            padding: 4.5rem 1.5rem 3rem; 
            text-align: center; 
            background: <?php echo $cover_path ? "url('".htmlspecialchars($cover_path)."')" : "var(--brand)"; ?>;
            background-size: cover;
            background-position: center;
            color: #fff;
            position: relative;
        }
        <?php if (!$cover_path): ?>
        .header::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.15;
            background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='1' fill-rule='evenodd'%3E%3Ccircle cx='3' cy='3' r='1'/%3E%3C/g%3E%3C/svg%3E");
        }
        <?php endif; ?>
        .header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(10, 47, 47, 0.4);
            <?php echo $cover_path ? "" : "display:none;"; ?>
        }
        .header-content { position: relative; z-index: 1; }
        .avatar { width: 90px; height: 90px; border-radius: 28px; background: #fff; margin: 0 auto 1.25rem; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; font-weight: 800; color: var(--brand); border: 4px solid rgba(255,255,255,0.2); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .name { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.7px; margin-bottom: 0.25rem; }
        .sub { font-size: 0.95rem; font-weight: 600; opacity: 0.9; }
        .sub span { margin: 0 5px; opacity: 0.5; }

        /* ── ACTIONS ───────────────────────────────────────── */
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding: 1.5rem; margin-top: -1.5rem; position: relative; z-index: 5; }
        .btn-action { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem; border-radius: 14px; text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: 0.2s; border: 1px solid #f1f5f9; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .btn-action:hover { opacity: 0.9; transform: translateY(-2px); }

        /* ── LINKS ─────────────────────────────────────────── */
        .links-list { padding: 0.75rem 1.5rem; }
        .link-row { display: flex; align-items: center; gap: 1rem; padding: 1rem 0.75rem; text-decoration: none; color: var(--ink); border-bottom: 1px solid #f8fafc; transition: 0.2s; }
        .link-row:last-child { border-bottom: none; }
        .link-row:hover { background: #fcfdfe; transform: translateX(5px); }
        .link-icon { width: 40px; height: 40px; background: #f8fafc; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--brand); }
        .link-info { flex: 1; }
        .link-label { font-weight: 700; font-size: 0.95rem; }
        .link-url { font-size: 0.8rem; color: var(--ink-lt); display: block; margin-top: 2px; }

        .vcard-fab { background: #A6803F; color: #fff; }

        @media (max-width: 480px) {
            .card { min-height: auto; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="header-content">
                <div class="avatar">
                    <?php if ($photo_path): ?><img src="<?php echo htmlspecialchars($photo_path); ?>"><?php else: echo $initial; endif; ?>
                </div>
                <h1 class="name"><?php echo htmlspecialchars($full_name); ?></h1>
                <div class="sub">
                    <?php if($title_text): echo htmlspecialchars($title_text); endif; ?>
                    <?php if($title_text && $company): ?><span>•</span><?php endif; ?>
                    <?php if($company): echo htmlspecialchars($company); endif; ?>
                </div>
                <?php if ($bio): ?>
                    <p style="font-size: 0.9rem; margin-top: 1.25rem; font-weight: 500; line-height: 1.6; max-width: 320px; margin-inline: auto; opacity: 0.9;">
                        <?php echo htmlspecialchars($bio); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="actions">
            <?php if($phone_work): ?>
                <a href="tel:<?php echo preg_replace('/[^0-9+]/','', $phone_work); ?>" class="btn-action btn-secondary" style="background:#000; color:#fff; border:none; grid-column: span 2; border-radius:12px; font-weight:800; padding: 1.15rem;">
                    <i data-lucide="phone"></i> Hemen Ara
                </a>
            <?php endif; ?>
            <a href="processes/generate_vcard.php?slug=<?php echo urlencode($slug); ?>" class="btn-action btn-secondary" style="border: 1.5px solid #000; border-radius:12px; grid-column: span 2;">
                <i data-lucide="user-plus" style="color:#000;"></i> Rehbere Kaydet
            </a>
            <?php if($email_work): ?>
                <a href="mailto:<?php echo htmlspecialchars($email_work); ?>" class="btn-action btn-secondary" style="border: 1.5px solid #e2e8f0; border-radius:12px; grid-column: span 2;">
                    <i data-lucide="mail"></i> E-posta Gönder
                </a>
            <?php endif; ?>
        </div>

        <?php if(!empty($social_links)): ?>
            <div class="links-list" style="padding-top: 0;">
                <?php foreach($social_links as $link): 
                    $p = strtolower($link['platform']);
                    $meta = $platform_meta[$p] ?? ['icon'=>'link-2'];
                ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="link-row" style="border-bottom: 1px solid #f8fafc; padding: 0.85rem 0.5rem;">
                        <div class="link-icon" style="width:36px; height:36px; background:none; border:none; color:#1e293b;"><i data-lucide="<?php echo $meta['icon']; ?>" style="width:20px;"></i></div>
                        <div class="link-info">
                            <span class="link-label" style="font-weight:600; font-size:0.9rem;"><?php echo ucwords($p); ?></span>
                        </div>
                        <i data-lucide="arrow-up-right" style="width:16px; color: #cbd5e1;"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: auto; padding: 2rem 1.5rem; text-align: center; border-top: 1px solid #f8fafc;">
            <div style="font-size: 0.65rem; font-weight:800; letter-spacing: 2px; color: #cbd5e1;">ZEROSOFT PREMIUM</div>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
