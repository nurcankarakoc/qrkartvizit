<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/subscription.php';
require_once '../core/customer_access.php';

// Oturum kontrolu
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Kullanıcı bilgilerini ve son siparişi çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();
$user_name = trim((string)($user['name'] ?? 'Müşteri'));
if ($user_name === '') {
    $user_name = 'Müşteri';
}
$first_name_parts = preg_split('/\s+/', $user_name);
$first_name = trim((string)($first_name_parts[0] ?? 'Müşteri'));
if ($first_name === '') {
    $first_name = 'Müşteri';
}

$status_text = [
    'pending' => 'Onay Bekliyor',
    'pending_payment' => 'Ödeme Bekleniyor',
    'pending_design' => 'Tasarım Planlandı',
    'designing' => 'Tasarım Aşamasında',
    'awaiting_approval' => 'Onayınız Bekleniyor',
    'revision_requested' => 'Revize Talebi İletildi',
    'approved' => 'Onaylandı / Baskıda',
    'printing' => 'Baskıda',
    'shipping' => 'Kargoda',
    'completed' => 'Tamamlandı',
];
$package_labels = [
    'classic' => 'Klasik Paket',
    'panel' => 'Sadece Panel',
    'smart' => '1000 Baski + 1 Yillik Erisim',
];
$current_status = strtolower(trim((string)($order['status'] ?? 'pending')));
if ($current_status === 'disputed') {
    $current_status = 'revision_requested';
}
$current_package = strtolower(trim((string)($order['package'] ?? '')));
$current_package_label = $package_labels[$current_package] ?? ($current_package !== '' ? ucfirst($current_package) : 'Henüz seçilmedi');
$remaining_revisions = (int)($order['revision_count'] ?? 0);
$package_state = qrk_get_customer_package_state($pdo, $user_id, $current_package);
$current_package_definition = $package_state['definition'];
$pending_package_definition = (array)($package_state['pending_definition'] ?? []);
$remaining_order_credits = $package_state['remaining_order_credits'];
$can_create_order = $package_state['can_create_order'];
$has_active_package = (string)($package_state['package_slug'] ?? '') !== '';
$pending_package_slug = (string)($package_state['pending_package_slug'] ?? '');
$has_pending_package = $pending_package_slug !== '';
$display_package_definition = $has_active_package
    ? $current_package_definition
    : ($has_pending_package ? $pending_package_definition : $current_package_definition);

$all_package_definitions = qrk_get_all_package_definitions($pdo);
$active_package_definitions = array_filter(
    $all_package_definitions,
    static fn(array $package): bool => (bool)($package['is_active'] ?? true)
);
if ($active_package_definitions === []) {
    $active_package_definitions = $all_package_definitions;
}
if ($current_package === '' && $package_state['package_slug'] !== '') {
    $current_package_label = (string)$current_package_definition['label'];
} elseif (!$has_active_package && $has_pending_package) {
    $current_package_label = (string)($display_package_definition['label'] ?? 'Secili Paket');
}

function project_base_url_for_customer_panel(): string
{
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/customer/dashboard.php');
    $project_path = preg_replace('#/customer/[^/]+$#', '', $script_name);

    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

$package_value = $package_state['package_slug'] !== '' ? $package_state['package_slug'] : (string)($order['package'] ?? '');
$is_digital_profile_active_for_package = qrk_user_has_digital_access($pdo, $user_id, $package_value);
$profile_slug = trim((string)($profile['slug'] ?? ''));
$public_profile_url = '';

if ($is_digital_profile_active_for_package && $profile_slug !== '') {
    $public_profile_url = project_base_url_for_customer_panel()
        . '/kartvizit.php?slug='
        . rawurlencode($profile_slug);
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli - Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #0A2F2F;
            --content-bg: #f8fafc;
            --primary: #A6803F;
            --gold: #A6803F;
            --gold-light: #C5A059;
            --navy-blue: #0A2F2F;
            --navy-dark: #072424;
        }

        body {
            background: var(--content-bg);
            display: block;
            min-height: 100vh;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
            z-index: 100;
        }

        .sidebar-header {
            padding: 2.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-menu {
            padding: 1.5rem;
            flex: 1;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
            font-weight: 500;
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        .menu-item i { width: 20px; height: 20px; }

        /* Main Content area */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 3rem;
            min-width: 0;
        }

        .top-bar {
            gap: 1.25rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        .top-bar > * {
            min-width: 0;
        }

        .dashboard-mini-grid {
            display:grid;
            grid-template-columns:repeat(2, minmax(0, 1fr));
            gap:0.85rem;
            margin-top:1rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        .dashboard-header h1 {
            font-size: 2rem;
            font-weight: 800;
        }

        /* Stats & Info Cards */
        .status-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .card {
            background:
                linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97)) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid transparent;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(10, 47, 47, 0.03);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 1;
        }
        .card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            padding: 1px;
            background: linear-gradient(135deg, var(--gold), transparent 60%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: -1;
            pointer-events: none;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(166, 128, 63, 0.08);
        }
        .card:hover::before {
            opacity: 1;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-awaiting_approval { background: #fff7ed; color: #9a3412; }
        .status-designing { background: #eff6ff; color: var(--navy-blue); border: 1px solid #dbeafe; }
        .status-revision_requested { background: #fff1f2; color: #be123c; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-printing { background: #e0f2fe; color: #0c4a6e; }
        .status-shipping { background: #ede9fe; color: #5b21b6; }
        .status-completed { background: #f1f5f9; color: #475569; }

        .profile-preview-card {
            text-align: center;
        }

        .avatar-lg {
            width: 100px;
            height: 100px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(166, 128, 63, 0.2);
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 1px solid transparent;
            background:
                linear-gradient(#f8fafc, #f8fafc) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: var(--navy-blue);
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.3s ease;
            margin-top: 1rem;
        }

        .btn-primary-action {
            border: 1px solid transparent;
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            box-shadow: 0 12px 24px rgba(6, 36, 36, 0.24);
        }
        .btn-primary-action:hover {
            background:
                linear-gradient(135deg, #0a3c3c, #125757) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(166, 128, 63, 0.25);
        }

        .onboarding-box {
            margin-bottom: 1.25rem;
            border-radius: 20px;
            border: 1px solid #dbeafe;
            background: linear-gradient(140deg, #eff6ff 0%, #f8fafc 58%, #ffffff 100%);
            padding: 1.2rem 1.25rem;
        }

        .onboarding-steps {
            margin: 0.85rem 0 0;
            padding-left: 1.1rem;
            color: #334155;
            line-height: 1.65;
            display: grid;
            gap: 0.45rem;
        }

        .package-preview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
            margin-top: 1rem;
        }

        .package-preview-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.95rem;
            background: #fff;
        }

        .package-preview-card h4 {
            margin: 0;
            color: #0A2F2F;
            font-size: 1rem;
            font-weight: 900;
        }

        .package-preview-price {
            margin-top: 0.25rem;
            font-size: 0.95rem;
            font-weight: 800;
            color: #A6803F;
        }

        .package-preview-desc {
            margin-top: 0.4rem;
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }

        .package-preview-list {
            margin: 0.6rem 0 0;
            padding-left: 1rem;
            color: #334155;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .mobile-topbar,
        .sidebar-overlay {
            display: none;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .mobile-topbar {
                position: sticky;
                top: 0;
                z-index: 1200;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                background: #fff;
                border-bottom: 1px solid #e2e8f0;
                padding: 0.75rem 1rem;
            }

            .mobile-topbar__brand {
                font-size: 0.95rem;
                font-weight: 800;
                color: var(--navy-blue);
                flex: 1;
                text-align: center;
            }

            .mobile-nav-toggle {
                width: 44px;
                height: 44px;
                border: 1px solid #dbeafe;
                background: #eff6ff;
                color: var(--navy-blue);
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }

            .sidebar {
                width: min(86vw, 320px);
                transform: translateX(-110%);
                transition: transform 0.28s ease;
                z-index: 1300;
            }

            .sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.45);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
                z-index: 1250;
            }

            .sidebar-overlay.is-open {
                opacity: 1;
                pointer-events: auto;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .status-grid {
                grid-template-columns: 1fr;
            }
            .package-preview-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .btn-action,
            .menu-item,
            button,
            .mobile-nav-toggle {
                min-height: 44px;
                min-width: 44px;
            }
            .order-snapshot-row {
                flex-direction: column;
                align-items: stretch;
                gap: 0.85rem;
            }
            .order-snapshot-row > div {
                width: 100%;
            }
            .dashboard-header h1 {
                font-size: 1.6rem;
            }
            .top-bar {
                flex-direction: column;
                align-items: stretch;
                text-align: left;
                padding: 1.5rem !important;
            }
            .top-bar > * {
                width: 100%;
            }
            .user-pill {
                width: 100% !important;
                justify-content: space-between !important;
                margin-top: 0.5rem;
            }
            .dashboard-mini-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }
            .card {
                padding: 1.25rem 1rem;
                border-radius: 16px;
            }
            .dashboard-header h1 {
                font-size: 1.45rem;
            }
            .status-badge {
                font-size: 0.72rem;
                padding: 0.45rem 0.75rem;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>

                <li><a href="new-order.php"><i data-lucide="plus-circle"></i> Yeni Kartvizit Siparişi</a></li>
                <li><a href="profile.php"><i data-lucide="user-cog"></i> Dijital Profilim</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="details">
                    <span class="name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="role">Premium Üye</span>
                </div>
            </div>
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Merhaba, <?php echo htmlspecialchars($first_name); ?></h1>
                <p style="color: #64748b; margin-top: 0.5rem;">
                    <?php if ($has_active_package): ?>
                        Panelinize hoş geldiniz. Dijital varlığınızın özetini buradan takip edebilirsiniz.
                    <?php elseif ($has_pending_package): ?>
                        Paket seçiminiz hazır. Satın alma modülü şimdilik pasif; yine de panelinizin genel görününü buradan takip edebilirsiniz.
                    <?php else: ?>
                        Hesabınız hazır. Bölümdeki adımları takip ederek profilinizi oluşturun ve siparişinizi başlatın.
                    <?php endif; ?>
                </p>
            </div>
            <div class="user-pill" style="display: flex; align-items: center; gap: 1rem; background: #fff; padding: 0.6rem 1.4rem; border-radius: 50px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <span style="font-weight: 700; color: var(--navy-blue);"><?php echo htmlspecialchars($user_name); ?></span>
                <div style="width: 32px; height: 32px; background: var(--gold); color:#fff; border-radius: 12px; display: flex; align-items:center; justify-content: center; font-size: 0.85rem; font-weight: 900; box-shadow: 0 4px 10px rgba(166,128,63,0.3);">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="content-wrapper">


        <div class="status-grid">
            <!-- Order status card -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h3 style="font-weight: 800; font-size: 1.25rem;">Güncel Sipariş Durumu</h3>
                    <?php if ($order): ?>
                        <span class="status-badge status-<?php echo htmlspecialchars($current_status); ?>">
                            <i data-lucide="clock" style="width: 14px;"></i>
                            <?php echo htmlspecialchars($status_text[$current_status] ?? 'Beklemede'); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($order): ?>
                    <div class="order-snapshot-row" style="display: flex; gap: 2rem; align-items: center;">
                        <div style="flex: 1;">
                            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">Paket:</p>
                            <p style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($current_package_label); ?></p>
                        </div>
                        <div style="flex: 1;">
                            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">Kalan Revize Hakkı:</p>
                            <p style="font-weight: 700; font-size: 1.1rem;"><?php echo $remaining_revisions; ?> Hak</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1rem 1.1rem; color:#475569; font-weight:600;">
                        <?php if ($has_active_package): ?>
                            Henüz bir siparişiniz bulunmuyor. Baskılı ve dijital kartvizit siparişlerinizi panelden doğrudan oluşturabilirsiniz.
                        <?php elseif ($has_pending_package): ?>
                            Paket seçiminiz kayıtlı. Satın alma modülü açıldığında bu paket üzerinden devam edeceğiz.
                        <?php else: ?>
                            Hesabınız aktif. Sipariş başlatmadan önce paketinizi seçmeniz gerekiyor.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:0.85rem; margin-top:1rem;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:0.95rem 1rem;">
                        <div style="font-size:0.78rem; color:#64748b; font-weight:800; text-transform:uppercase;">Aktif Paket</div>
                        <div style="font-size:1rem; font-weight:900; color:#0A2F2F; margin-top:0.35rem;"><?php echo htmlspecialchars((string)($display_package_definition['label'] ?? $current_package_definition['label'])); ?></div>
                    </div>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:0.95rem 1rem;">
                        <div style="font-size:0.78rem; color:#64748b; font-weight:800; text-transform:uppercase;">Dijital Profil</div>
                        <div style="font-size:1rem; font-weight:900; color:<?php echo $is_digital_profile_active_for_package ? '#166534' : '#9a3412'; ?>; margin-top:0.35rem;">
                            <?php echo $is_digital_profile_active_for_package ? 'Aktif' : 'Bu pakette yok'; ?>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #f1f5f9;">
                    <?php if ($order): ?>
                        <a href="design-tracking.php" class="btn-action btn-primary-action">
                            Tasarımı Görüntüle ve Yönet <i data-lucide="arrow-right"></i>
                        </a>
                        <?php if ($can_create_order): ?>
                        <a href="new-order.php" class="btn-action">
                            Yeni Sipariş Oluştur <i data-lucide="plus-circle"></i>
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($can_create_order): ?>
                        <a href="new-order.php" class="btn-action btn-primary-action">
                            Yeni Sipariş Başlat <i data-lucide="arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <a href="profile.php" class="btn-action btn-primary-action">
                            Profilimi Düzenle <i data-lucide="edit-3"></i>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Preview Card -->
            <div class="card profile-preview-card">
                <div class="avatar-lg">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <h3 style="font-weight: 800;"><?php echo htmlspecialchars($user_name); ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($profile['title'] ?? 'Unvan Belirtilmedi'); ?></p>
                
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; margin-bottom: 1.5rem;">
                    <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem;">Dijital Kartvizit Linkiniz:</p>
                    <?php if ($public_profile_url !== ''): ?>
                        <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" rel="noopener" style="color: var(--primary); font-weight: 700; text-decoration: none; font-size: 0.9rem; word-break: break-all;">
                            <?php echo htmlspecialchars($public_profile_url); ?>
                        </a>
                    <?php elseif (!$is_digital_profile_active_for_package): ?>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0;">Bu pakette dijital profil aktif değil. Dijital erişim için Panel veya Akıllı paket gerekir.</p>
                    <?php else: ?>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0;">Profil linkiniz oluşturuluyor. Profil bilgilerinizi kaydedip tekrar deneyin.</p>
                    <?php endif; ?>
                </div>

                <a href="profile.php" class="btn-action" style="background: #f1f5f9; color: #1e293b;">
                    Dijital Kartı Düzenle <i data-lucide="edit-3"></i>
                </a>
            </div>
        </div>
        </div> <!-- content-wrapper end -->
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
