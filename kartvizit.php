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
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: radial-gradient(circle at top, #f8fafc, #e2e8f0);
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #0f172a;
            }
            .box {
                width: min(560px, calc(100% - 2rem));
                padding: 2rem;
                border: 1px solid #cbd5e1;
                border-radius: 18px;
                background: #ffffff;
                box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
                text-align: center;
            }
            h1 { margin: 0 0 0.75rem; font-size: 1.35rem; }
            p { margin: 0; color: #475569; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="box">
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

$title = trim((string)($profile['title'] ?? ''));
$company = trim((string)($profile['company'] ?? ''));
$bio = trim((string)($profile['bio'] ?? ''));
$phone_work = trim((string)($profile['phone_work'] ?? ''));
$email_work = trim((string)($profile['email_work'] ?? ''));
$photo_path = trim((string)($profile['photo_path'] ?? ''));

$initial = strtoupper(substr($full_name !== '' ? $full_name : 'U', 0, 1));
$phone_href = $phone_work !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $phone_work) : '';
$email_href = $email_work !== '' ? 'mailto:' . $email_work : '';

$platform_meta = [
    'instagram' => ['label' => 'Instagram', 'icon' => 'instagram'],
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'message-circle'],
    'linkedin' => ['label' => 'LinkedIn', 'icon' => 'linkedin'],
    'website' => ['label' => 'Web Sitesi', 'icon' => 'globe'],
    'mail' => ['label' => 'E-Posta', 'icon' => 'mail'],
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?> | Dijital Kartvizit</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --brand: #0a2f2f;
            --gold: #a6803f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(900px 480px at 15% -10%, rgba(166, 128, 63, 0.15), transparent 60%),
                radial-gradient(800px 420px at 100% 0%, rgba(10, 47, 47, 0.12), transparent 60%),
                var(--bg);
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
        }
        .wrap {
            max-width: 720px;
            margin: 0 auto;
            padding: 2rem 1rem 3rem;
        }
        .card {
            background: var(--card);
            border: 1px solid #dbe2ea;
            border-radius: 26px;
            padding: 2rem 1.25rem;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08);
        }
        .avatar {
            width: 112px;
            height: 112px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), #124a4a);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 2.25rem;
            font-weight: 800;
            overflow: hidden;
            border: 4px solid #fff;
            box-shadow: 0 16px 28px rgba(10, 47, 47, 0.22);
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        h1 {
            margin: 0;
            text-align: center;
            font-size: clamp(1.35rem, 3.8vw, 2rem);
            font-weight: 800;
        }
        .subtitle {
            margin: 0.4rem 0 0;
            text-align: center;
            color: var(--muted);
            line-height: 1.5;
        }
        .bio {
            margin: 1.2rem auto 0;
            max-width: 92%;
            color: #334155;
            text-align: center;
            line-height: 1.6;
        }
        .actions {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            border: 1px solid #d4dce5;
            color: var(--ink);
            background: #fff;
        }
        .btn.primary {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }
        .btn i { width: 16px; height: 16px; }
        .links {
            margin-top: 1.4rem;
            display: grid;
            gap: 0.65rem;
        }
        .social {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 0.85rem;
            text-decoration: none;
            color: #0f172a;
            background: #fff;
        }
        .social .left {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 0;
        }
        .social .left i { width: 16px; height: 16px; color: var(--gold); }
        .social .text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #334155;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="avatar">
                <?php if ($photo_path !== ''): ?>
                    <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profil Fotografi">
                <?php else: ?>
                    <?php echo htmlspecialchars($initial); ?>
                <?php endif; ?>
            </div>

            <h1><?php echo htmlspecialchars($full_name); ?></h1>
            <p class="subtitle">
                <?php echo htmlspecialchars($title !== '' ? $title : ''); ?>
                <?php if ($title !== '' && $company !== ''): ?> | <?php endif; ?>
                <?php echo htmlspecialchars($company !== '' ? $company : ''); ?>
            </p>

            <?php if ($bio !== ''): ?>
                <p class="bio"><?php echo nl2br(htmlspecialchars($bio)); ?></p>
            <?php endif; ?>

            <div class="actions">
                <?php if ($phone_href !== ''): ?>
                    <a class="btn primary" href="<?php echo htmlspecialchars($phone_href); ?>">
                        <i data-lucide="phone"></i> Ara
                    </a>
                <?php endif; ?>

                <?php if ($email_href !== ''): ?>
                    <a class="btn" href="<?php echo htmlspecialchars($email_href); ?>">
                        <i data-lucide="mail"></i> E-posta Gonder
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($social_links)): ?>
                <div class="links">
                    <?php foreach ($social_links as $link): ?>
                        <?php
                        $platform = strtolower((string)($link['platform'] ?? ''));
                        $url = (string)($link['url'] ?? '');
                        if ($url === '') {
                            continue;
                        }
                        $meta = $platform_meta[$platform] ?? ['label' => ucfirst($platform), 'icon' => 'link-2'];
                        ?>
                        <a class="social" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">
                            <span class="left">
                                <i data-lucide="<?php echo htmlspecialchars($meta['icon']); ?>"></i>
                                <strong><?php echo htmlspecialchars($meta['label']); ?></strong>
                            </span>
                            <span class="text"><?php echo htmlspecialchars($url); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
