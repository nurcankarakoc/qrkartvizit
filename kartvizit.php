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
        body{min-height:100vh;display:grid;place-items:center;background:#f0f4f4;font-family:Inter,sans-serif;padding:2rem;}
        .box{width:min(420px,100%);padding:2.5rem 2rem;text-align:center;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(10,47,47,.1);border:1px solid rgba(10,47,47,.08)}
        .icon{font-size:2.5rem;margin-bottom:1.2rem}
        h1{font-size:1.25rem;font-weight:800;color:#0A2F2F;margin-bottom:.6rem}
        p{color:#64748b;line-height:1.7;font-size:.9rem}
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🔒</div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo htmlspecialchars($msg); ?></p>
    </div>
</body></html><?php
    exit();
}

/* ── Veri çekimi ─────────────────────────────────────────────────── */
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
$is_expired     = false;
if (table_has_column($pdo,'profiles','expiry_date') && !empty($profile['expiry_date'])) {
    $is_expired = strtotime($profile['expiry_date'].' 23:59:59') < time();
}
if (!$profile_active || !$user_active || $is_expired) {
    render_state_page(410, 'Profil Yayın Dışı', 'Bu dijital kartvizit şu an görüntülenemiyor.');
}

if (table_has_column($pdo,'profiles','view_count')) {
    $pdo->prepare("UPDATE profiles SET view_count=COALESCE(view_count,0)+1 WHERE id=?")->execute([(int)$profile['id']]);
}

$stmt = $pdo->prepare("SELECT platform,url FROM social_links WHERE profile_id=? ORDER BY id ASC");
$stmt->execute([(int)$profile['id']]);
$social_links = $stmt->fetchAll();

/* ── Değişkenler ─────────────────────────────────────────────────── */
$full_name   = trim((string)($profile['full_name'] ?? '')) ?: trim((string)($profile['owner_name'] ?? 'Dijital Kartvizit'));
$title_text  = trim((string)($profile['title']      ?? ''));
$company     = trim((string)($profile['company']    ?? ''));
$bio         = trim((string)($profile['bio']        ?? ''));
$phone_work  = trim((string)($profile['phone_work'] ?? ''));
$email_work  = trim((string)($profile['email_work'] ?? ''));
$photo_path  = trim((string)($profile['photo_path'] ?? ''));
$cover_photo = trim((string)($profile['cover_photo'] ?? ''));
$brand_color_raw = trim((string)($profile['brand_color'] ?? ''));
if ($brand_color_raw === '') {
    $brand_color_raw = trim((string)($profile['theme_color'] ?? ''));
}
// Özel marka rengi doğrulama
$brand_color = preg_match('/^#[0-9a-fA-F]{6}$/', $brand_color_raw) ? strtoupper($brand_color_raw) : '#0A2F2F';

// Açık renk mi karanlık mı? (kontrast için metin rengi)
[$rr,$gg,$bb] = sscanf($brand_color, "#%02x%02x%02x");
$luma = (0.299*$rr + 0.587*$gg + 0.114*$bb) / 255;
$hero_text_color = $luma > 0.55 ? '#0f1a1a' : '#ffffff';
$hero_text_muted = $luma > 0.55 ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.65)';

// Daha koyu/açık varyant (gradient için)
function adjust_hex(string $hex, int $amount): string {
    [$r,$g,$b] = sscanf($hex, "#%02x%02x%02x");
    $r = max(0,min(255,$r+$amount));
    $g = max(0,min(255,$g+$amount));
    $b = max(0,min(255,$b+$amount));
    return sprintf('#%02x%02x%02x',$r,$g,$b);
}
$brand_dark = adjust_hex($brand_color, -30);

$initial    = strtoupper(substr($full_name,0,1));
$phone_href = $phone_work !== '' ? 'tel:'.preg_replace('/[^0-9+]/','', $phone_work) : '';
$email_href = $email_work !== '' ? 'mailto:'.$email_work : '';

$platform_meta = [
    'instagram' => ['label'=>'Instagram',  'icon'=>'instagram',      'color'=>'#E1306C'],
    'whatsapp'  => ['label'=>'WhatsApp',   'icon'=>'message-circle', 'color'=>'#25D366'],
    'linkedin'  => ['label'=>'LinkedIn',   'icon'=>'linkedin',       'color'=>'#0A66C2'],
    'website'   => ['label'=>'Web Sitesi', 'icon'=>'globe',          'color'=>'#6366f1'],
    'x'         => ['label'=>'X / Twitter','icon'=>'twitter',        'color'=>'#111827'],
    'twitter'   => ['label'=>'Twitter',    'icon'=>'twitter',        'color'=>'#1DA1F2'],
    'youtube'   => ['label'=>'YouTube',    'icon'=>'youtube',        'color'=>'#FF0000'],
    'github'    => ['label'=>'GitHub',     'icon'=>'github',         'color'=>'#24292e'],
    'facebook'  => ['label'=>'Facebook',   'icon'=>'facebook',       'color'=>'#1877F2'],
    'tiktok'    => ['label'=>'TikTok',     'icon'=>'music',          'color'=>'#010101'],
    'telegram'  => ['label'=>'Telegram',   'icon'=>'send',           'color'=>'#0088cc'],
    'behance'   => ['label'=>'Behance',    'icon'=>'layers',         'color'=>'#053eff'],
    'dribbble'  => ['label'=>'Dribbble',   'icon'=>'dribbble',       'color'=>'#ea4c89'],
    'medium'    => ['label'=>'Medium',     'icon'=>'book-open',      'color'=>'#292929'],
    'mail'      => ['label'=>'E-Posta',    'icon'=>'mail',           'color'=>'#A6803F'],
    'phone'     => ['label'=>'Telefon',    'icon'=>'phone',          'color'=>'#10b981'],
    'maps'      => ['label'=>'Konum',      'icon'=>'map-pin',        'color'=>'#f43f5e'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?> | Dijital Kartvizit</title>
    <meta name="description" content="<?php echo htmlspecialchars($full_name.($title_text?" — $title_text":'').($company?" | $company":'')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* ── TOKENS ───────────────────────────────────────── */
        :root {
            --brand:      <?php echo $brand_color; ?>;
            --brand-dk:   <?php echo $brand_dark; ?>;
            --gold:       #A6803F;
            --gold-lt:    #C5A059;
            --hero-text:  <?php echo $hero_text_color; ?>;
            --hero-muted: <?php echo $hero_text_muted; ?>;
            --bg:         #f2f5f5;
            --surface:    #ffffff;
            --ink:        #111827;
            --ink-2:      #374151;
            --ink-3:      #6b7280;
            --border:     #e5e7eb;
            --radius:     20px;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;
            background:var(--bg);
            color:var(--ink);
            min-height:100vh;
        }

        /* ── CARD SHELL ───────────────────────────────────── */
        .page{
            max-width: 420px;
            margin: 0 auto;
            min-height: 100vh;
            background: var(--surface);
            box-shadow: 0 0 0 1px rgba(0,0,0,.04), 0 32px 80px rgba(0,0,0,.1);
            display: flex;
            flex-direction: column;
        }

        /* ── HERO ─────────────────────────────────────────── */
        .hero{
            position: relative;
            background: linear-gradient(160deg, var(--brand) 0%, var(--brand-dk) 100%);
            padding: 0 0 48px;
            overflow: hidden;
        }
        .hero-noise{
            position:absolute;inset:0;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events:none;
        }

        /* Cover photo */
        .cover{
            width:100%; height:130px;
            object-fit:cover;
            display:block;
            opacity:.85;
        }
        .cover-placeholder{
            height:80px;
            background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.15) 100%);
        }

        .hero-body{
            position:relative;
            z-index:1;
            text-align:center;
            padding: 0 1.5rem;
            margin-top:-44px;
        }

        /* Avatar */
        .avatar{
            width:88px; height:88px;
            border-radius:50%;
            border:3px solid var(--surface);
            box-shadow:0 4px 20px rgba(0,0,0,.18);
            background:linear-gradient(135deg, var(--brand), var(--brand-dk));
            color:var(--hero-text);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:2rem;
            font-weight:800;
            overflow:hidden;
            margin-bottom:.85rem;
        }
        .avatar img{width:100%;height:100%;object-fit:cover;}

        .hero-name{
            font-size:1.45rem;
            font-weight:800;
            color:var(--hero-text);
            letter-spacing:-.3px;
            line-height:1.2;
        }
        .hero-sub{
            margin-top:.3rem;
            font-size:.83rem;
            color:var(--hero-muted);
            font-weight:500;
        }
        .hero-sub .sep{color:var(--gold-lt);margin:0 .35rem;}

        /* ── BODY ─────────────────────────────────────────── */
        .body{
            flex:1;
            padding: 1.5rem 1.25rem 2.5rem;
        }

        /* bio */
        .bio{
            font-size:.855rem;
            color:var(--ink-2);
            line-height:1.7;
            text-align:center;
            padding:.85rem 1rem;
            background:#f9fafb;
            border-radius:14px;
            border:1px solid var(--border);
            margin-bottom:1.25rem;
        }

        /* ── BUTTONS ──────────────────────────────────────── */
        .btns{display:grid;gap:.6rem;margin-bottom:1.5rem;}
        .btn{
            display:flex;align-items:center;justify-content:center;gap:.55rem;
            text-decoration:none;
            padding:.8rem 1rem;
            border-radius:13px;
            font-weight:700;font-size:.875rem;
            border:none;cursor:pointer;
            transition:transform .15s,box-shadow .15s,filter .15s;
        }
        .btn:active{transform:scale(.97);}
        .btn i{width:16px;height:16px;flex-shrink:0;}

        .btn-primary{
            background:var(--brand);
            color:var(--hero-text);
            box-shadow:0 4px 16px rgba(0,0,0,.14);
        }
        .btn-primary:hover{filter:brightness(1.08);box-shadow:0 6px 20px rgba(0,0,0,.2);}

        .btn-secondary{
            background:#fff;
            color:var(--ink);
            border:1.5px solid var(--border);
            box-shadow:0 2px 8px rgba(0,0,0,.04);
        }
        .btn-secondary:hover{border-color:#d1d5db;box-shadow:0 4px 12px rgba(0,0,0,.08);}

        .btn-gold{
            background:linear-gradient(135deg,var(--gold),var(--gold-lt));
            color:#fff;
            box-shadow:0 4px 16px rgba(166,128,63,.25);
        }
        .btn-gold:hover{filter:brightness(1.06);box-shadow:0 6px 20px rgba(166,128,63,.35);}

        @media(min-width:400px){
            .btns{grid-template-columns:1fr 1fr;}
            .btn-gold{grid-column:1/-1;}
        }

        /* ── LINKS DIVIDER ────────────────────────────────── */
        .divider{
            display:flex;align-items:center;gap:.6rem;
            margin:.25rem 0 .85rem;
        }
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border);}
        .divider span{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--ink-3);}

        /* ── SOCIAL LINKS ─────────────────────────────────── */
        .links{display:grid;gap:.5rem;}

        .link-row{
            display:flex;align-items:center;gap:.75rem;
            padding:.7rem .85rem;
            background:#fff;
            border:1.5px solid var(--border);
            border-radius:12px;
            text-decoration:none;
            color:var(--ink);
            transition:transform .15s,border-color .15s,box-shadow .15s;
            position:relative;overflow:hidden;
        }
        .link-row::after{
            content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
            background:var(--pc,var(--gold));
            transform:scaleY(0);transition:transform .2s;
            border-radius:0 3px 3px 0;
        }
        .link-row:hover{transform:translateX(3px);border-color:rgba(0,0,0,.12);box-shadow:0 2px 12px rgba(0,0,0,.06);}
        .link-row:hover::after{transform:scaleY(1);}

        .link-icon{
            width:32px;height:32px;border-radius:9px;
            background:#f3f4f6;
            display:flex;align-items:center;justify-content:center;
            flex-shrink:0;
        }
        .link-icon i{width:15px;height:15px;color:var(--pc,var(--gold));}

        .link-info{flex:1;min-width:0;}
        .link-label{display:block;font-size:.78rem;font-weight:700;color:var(--ink);}
        .link-url{
            display:block;font-size:.71rem;color:var(--ink-3);
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        }
        .link-arrow i{width:13px;height:13px;color:var(--ink-3);transition:transform .15s;}
        .link-row:hover .link-arrow i{transform:translateX(3px);color:var(--brand);}

        /* ── FOOTER ───────────────────────────────────────── */
        .footer{
            text-align:center;
            padding:.75rem 1rem 1.5rem;
            border-top:1px solid var(--border);
        }
        .footer a{
            font-size:.65rem;font-weight:600;letter-spacing:1.5px;
            text-transform:uppercase;color:var(--ink-3);text-decoration:none;
            display:inline-flex;align-items:center;gap:.35rem;
        }
        .footer a:hover{color:var(--brand);}
        .footer .dot{width:5px;height:5px;background:var(--gold);border-radius:50%;display:inline-block;}

        /* ── ANIMATIONS ───────────────────────────────────── */
        @keyframes up{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
        .page{animation:up .5s ease both;}
        .link-row{opacity:0;animation:up .35s ease forwards;}

        /* ── WIDE SCREEN: center the card ────────────────── */
        @media(min-width:500px){
            body{display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem 4rem;}
            .page{border-radius:24px;min-height:auto;max-height:none;}
        }
    </style>
</head>
<body>
<div class="page" id="kv-page">

    <!-- HERO -->
    <div class="hero">
        <div class="hero-noise"></div>

        <?php if ($cover_photo !== ''): ?>
            <img class="cover" src="<?php echo htmlspecialchars($cover_photo); ?>" alt="Kapak">
        <?php else: ?>
            <div class="cover-placeholder"></div>
        <?php endif; ?>

        <div class="hero-body">
            <div class="avatar" id="kv-avatar">
                <?php if ($photo_path !== ''): ?>
                    <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                <?php else: ?>
                    <?php echo htmlspecialchars($initial); ?>
                <?php endif; ?>
            </div>
            <h1 class="hero-name" id="kv-name"><?php echo htmlspecialchars($full_name); ?></h1>
            <?php if ($title_text !== '' || $company !== ''): ?>
                <p class="hero-sub">
                    <?php if ($title_text !== ''): ?><?php echo htmlspecialchars($title_text); ?><?php endif; ?>
                    <?php if ($title_text !== '' && $company !== ''): ?><span class="sep">·</span><?php endif; ?>
                    <?php if ($company !== ''): ?><?php echo htmlspecialchars($company); ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- BODY -->
    <div class="body">

        <?php if ($bio !== ''): ?>
            <div class="bio"><?php echo nl2br(htmlspecialchars($bio)); ?></div>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="btns">
            <?php if ($phone_href !== ''): ?>
                <a class="btn btn-primary" href="<?php echo htmlspecialchars($phone_href); ?>" id="btn-call">
                    <i data-lucide="phone"></i> Ara
                </a>
            <?php endif; ?>
            <?php if ($email_href !== ''): ?>
                <a class="btn btn-secondary" href="<?php echo htmlspecialchars($email_href); ?>" id="btn-mail">
                    <i data-lucide="mail"></i> E-posta
                </a>
            <?php endif; ?>
            <?php if ($phone_work !== '' || $email_work !== ''): ?>
                <button class="btn btn-gold" onclick="downloadVCard()" id="btn-save">
                    <i data-lucide="contact"></i> Rehbere Kaydet
                </button>
            <?php endif; ?>
        </div>

        <!-- Social Links -->
        <?php if (!empty($social_links)): ?>
            <div class="divider"><span>Bağlantılar</span></div>
            <div class="links">
                <?php foreach ($social_links as $idx => $link): ?>
                    <?php
                    $plat    = strtolower(trim((string)($link['platform'] ?? '')));
                    $url     = (string)($link['url'] ?? '');
                    if ($url === '') continue;
                    $meta    = $platform_meta[$plat] ?? ['label'=>ucfirst($plat),'icon'=>'link-2','color'=>'#A6803F'];
                    $display = rtrim(preg_replace('#^https?://(www\.)?#', '', $url), '/');
                    $delay   = 100 + $idx * 60;
                    ?>
                    <a class="link-row"
                       href="<?php echo htmlspecialchars($url); ?>"
                       target="_blank" rel="noopener noreferrer"
                       style="--pc:<?php echo htmlspecialchars($meta['color']); ?>;animation-delay:<?php echo $delay; ?>ms"
                       id="link-<?php echo htmlspecialchars($plat.$idx); ?>">
                        <div class="link-icon">
                            <i data-lucide="<?php echo htmlspecialchars($meta['icon']); ?>"></i>
                        </div>
                        <div class="link-info">
                            <span class="link-label"><?php echo htmlspecialchars($meta['label']); ?></span>
                            <span class="link-url"><?php echo htmlspecialchars($display); ?></span>
                        </div>
                        <div class="link-arrow"><i data-lucide="arrow-right"></i></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <a href="/" id="footer-link">
            <span class="dot"></span>
            Zerosoft QR Kartvizit
            <span class="dot"></span>
        </a>
    </div>

</div><!-- /page -->

<script>
    lucide.createIcons();

    function downloadVCard() {
        const name    = <?php echo json_encode($full_name); ?>;
        const phone   = <?php echo json_encode($phone_work); ?>;
        const email   = <?php echo json_encode($email_work); ?>;
        const title   = <?php echo json_encode($title_text); ?>;
        const company = <?php echo json_encode($company); ?>;

        const lines = ['BEGIN:VCARD','VERSION:3.0','FN:'+name,'N:'+name.split(' ').reverse().join(';')+';;;'];
        if (title)   lines.push('TITLE:'+title);
        if (company) lines.push('ORG:'+company);
        if (phone)   lines.push('TEL;TYPE=WORK,VOICE:'+phone);
        if (email)   lines.push('EMAIL;TYPE=WORK:'+email);
        lines.push('END:VCARD');

        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(new Blob([lines.join('\r\n')], {type:'text/vcard'})),
            download: name.replace(/\s+/g,'_') + '.vcf'
        });
        a.click();
    }
</script>
</body>
</html>
