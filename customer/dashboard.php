<?php
session_start();
require_once '../core/db.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini ve son siparişi çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli — Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #0f172a;
            --content-bg: #f8fafc;
        }

        body {
            background: var(--content-bg);
            display: flex;
            min-height: 100vh;
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
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
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
        .status-designing { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #dcfce7; color: #166534; }

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
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.2);
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.3s;
            margin-top: 1rem;
        }

        .btn-primary-action { background: var(--primary); color: #fff; }
        .btn-primary-action:hover { background: #2563eb; transform: translateY(-2px); }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .sidebar-header, .menu-item span { display: none; }
            .main-content { margin-left: 80px; padding: 2rem; }
            .status-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span style="font-weight: 800; font-size: 1.2rem;">QR Kartvizit</span>
            </div>
        </div>
        <nav class="sidebar-menu">
            <a href="#" class="menu-item active">
                <i data-lucide="layout-dashboard"></i>
                <span>Genel Bakış</span>
            </a>
            <a href="profile.php" class="menu-item">
                <i data-lucide="user-cog"></i>
                <span>Profilimi Düzenle</span>
            </a>
            <a href="design-tracking.php" class="menu-item">
                <i data-lucide="palette"></i>
                <span>Tasarım Süreci</span>
            </a>
            <a href="#" class="menu-item">
                <i data-lucide="shopping-bag"></i>
                <span>Siparişlerim</span>
            </a>
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                <a href="../processes/logout.php" class="menu-item" style="color: #ef4444;">
                    <i data-lucide="log-out"></i>
                    <span>Çıkış Yap</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="dashboard-header">
            <div>
                <h1>Merhaba, <?php echo explode(' ', $user['name'])[0]; ?> 👋</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Panelinize hoş geldiniz. İşte dijital varlığınızın özeti.</p>
            </div>
            <div class="user-pill" style="display: flex; align-items: center; gap: 1rem; background: #fff; padding: 0.5rem 1.25rem; border-radius: 50px; border: 1px solid #e2e8f0;">
                <span style="font-weight: 600;"><?php echo $user['name']; ?></span>
                <div style="width: 32px; height: 32px; background: #3b82f6; color:#fff; border-radius: 50%; display: flex; align-items:center; justify-content: center; font-size: 0.8rem; font-weight: 800;">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="status-grid">
            <!-- Order status card -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h3 style="font-weight: 800; font-size: 1.25rem;">Güncel Sipariş Durumu</h3>
                    <span class="status-badge status-<?php echo ($order && isset($order['status'])) ? $order['status'] : 'pending'; ?>">
                        <i data-lucide="clock" style="width: 14px;"></i>
                        <?php 
                            $status_text = [
                                'pending' => 'Onay Bekliyor',
                                'designing' => 'Tasarım Aşamasında',
                                'approved' => 'Onaylandı / Baskıda',
                                'completed' => 'Tamamlandı'
                            ];
                            echo ($order && isset($order['status'])) ? ($status_text[$order['status']] ?? 'Beklemede') : 'Beklemede';
                        ?>
                    </span>
                </div>

                <div style="display: flex; gap: 2rem; align-items: center;">
                    <div style="flex: 1;">
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">Paket:</p>
                        <p style="font-weight: 700; font-size: 1.1rem;"><?php echo ucfirst($order['package'] ?? 'Akıllı Paket'); ?></p>
                    </div>
                    <div style="flex: 1;">
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.5rem;">Kalan Revize Hakkı:</p>
                        <p style="font-weight: 700; font-size: 1.1rem;"><?php echo $order['revision_count'] ?? 0; ?> Hak</p>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #f1f5f9;">
                    <a href="design-tracking.php" class="btn-action btn-primary-action">
                        Tasarımı Görüntüle & Yönet <i data-lucide="arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Profile Preview Card -->
            <div class="card profile-preview-card">
                <div class="avatar-lg">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <h3 style="font-weight: 800;"><?php echo $user['name']; ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;"><?php echo $profile['title'] ?? 'Ünvan Belirtilmedi'; ?></p>
                
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; margin-bottom: 1.5rem;">
                    <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem;">Dijital Kartvizit Linkiniz:</p>
                    <a href="#" style="color: var(--primary); font-weight: 700; text-decoration: none; font-size: 0.9rem;">
                        zerosoft.qr/<?php echo $profile['slug'] ?? 'kullanici'; ?>
                    </a>
                </div>

                <a href="profile.php" class="btn-action" style="background: #f1f5f9; color: #1e293b;">
                    Profili Düzenle <i data-lucide="edit-3"></i>
                </a>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
