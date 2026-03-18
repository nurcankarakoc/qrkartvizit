<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = $_GET['msg'] ?? '';

// Fetch Designers
$stmt_designers = $pdo->prepare("SELECT * FROM users WHERE role = 'designer' ORDER BY created_at DESC");
$stmt_designers->execute();
$designers = $stmt_designers->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarımcı Yönetimi — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .designer-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; }
        .designer-card { background: #fff; padding: 1.5rem; border-radius: 20px; box-shadow: var(--card-shadow); display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; border-left: 4px solid var(--primary); }
        .designer-info { display: flex; align-items: center; gap: 1rem; }
        .designer-info .avatar { width: 50px; height: 50px; background: #eff6ff; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; }
        .designer-details h4 { font-size: 1rem; font-weight: 800; margin-bottom: 0.2rem; }
        .designer-details p { font-size: 0.8rem; color: #64748b; }
        
        .add-designer-form { background: #fff; padding: 2rem; border-radius: 24px; box-shadow: var(--card-shadow); }
        .add-designer-form h2 { font-size: 1.2rem; font-weight: 800; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; color: #475569; }
        .form-control { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; }
        .btn-submit { width: 100%; padding: 1rem; background: var(--primary); color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.3s; margin-top: 1rem; }
        .btn-submit:hover { background: #2563eb; transform: translateY(-3px); }
    </style>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="brand-logotype" style="text-decoration: none;">
                    <div class="mock-logo">Z</div>
                    <span>Zerosoft <small>Admin</small></span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>
                    <li><a href="orders.php"><i data-lucide="shopping-cart"></i> Tüm Siparişler</a></li>
                    <li class="active"><a href="designers.php"><i data-lucide="users"></i> Tasarımcı Yönetimi</a></li>
                    <li><a href="disputes.php"><i data-lucide="alert-circle"></i> Uyuşmazlıklar</a></li>
                    <li><a href="#"><i data-lucide="settings"></i> Sistem Ayarları</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">A</div>
                    <div class="details"><span class="name">Süper Admin</span><span class="role">Zerosoft Yönetici</span></div>
                </div>
                <a href="../processes/logout.php" class="logout-btn"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1>Tasarımcı Yönetimi</h1>
                <?php if($message == 'added'): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">Tasarımcı başarıyla eklendi!</div>
                <?php endif; ?>
            </header>

            <div class="designer-grid">
                <div class="designers-list">
                    <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem;">Mevcut Tasarımcılar</h2>
                    <?php if(empty($designers)): ?>
                        <div class="empty-state">Henüz bir tasarımcı eklenmemiş.</div>
                    <?php else: ?>
                        <?php foreach($designers as $designer): ?>
                            <div class="designer-card">
                                <div class="designer-info">
                                    <div class="avatar"><?php echo strtoupper(substr($designer['name'], 0, 1)); ?></div>
                                    <div class="designer-details">
                                        <h4><?php echo htmlspecialchars($designer['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($designer['email']); ?></p>
                                    </div>
                                </div>
                                <div class="designer-actions">
                                    <button class="btn-action" style="color: #64748b;"><i data-lucide="more-horizontal"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="sidebar-pane">
                    <div class="add-designer-form">
                        <h2>Yeni Tasarımcı Ekle</h2>
                        <form action="../processes/admin_actions.php" method="POST">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="add_designer">
                            <div class="form-group">
                                <label>Ad Soyad</label>
                                <input type="text" name="name" class="form-control" placeholder="Örn: Mehmet Tasarımcı" required>
                            </div>
                            <div class="form-group">
                                <label>E-posta Adresi</label>
                                <input type="email" name="email" class="form-control" placeholder="designer@company.com" required>
                            </div>
                            <div class="form-group">
                                <label>Şifre Belirle</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <button type="submit" class="btn-submit">Sisteme Kaydet</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
