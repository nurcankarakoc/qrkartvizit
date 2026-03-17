<?php
session_start();
require_once '../core/db.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all disputes (pending first)
$stmt_disputes = $pdo->query("SELECT d.*, u.name as customer_name, o.id as order_id, o.package 
                              FROM disputes d 
                              JOIN users u ON d.user_id = u.id 
                              JOIN orders o ON d.order_id = o.id 
                              ORDER BY d.status DESC, d.created_at DESC");
$disputes = $stmt_disputes->fetchAll();

$message = $_GET['msg'] ?? '';

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uyuşmazlık Çözümü — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .dispute-card { background: #fff; padding: 2rem; border-radius: 20px; box-shadow: var(--card-shadow); margin-bottom: 2rem; border-left: 5px solid #ef4444; }
        .dispute-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
        .status-badge.pending { background: #fef2f2; color: #ef4444; }
        .status-badge.resolved_favor_customer { background: #dcfce7; color: #166534; }
        .status-badge.closed { background: #f1f5f9; color: #64748b; }
        .resolution-area { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; display: flex; gap: 1rem; }
        .btn-resolve { padding: 0.8rem 1.5rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 0.85rem; }
        .btn-resolve.customer { background: #16a34a; color: #fff; }
        .btn-resolve.designer { background: #334155; color: #fff; }
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
                    <li><a href="designers.php"><i data-lucide="users"></i> Tasarımcı Yönetimi</a></li>
                    <li class="active"><a href="disputes.php"><i data-lucide="alert-circle"></i> Uyuşmazlıklar</a></li>
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
                <h1>Uyuşmazlık Çözüm Merkezi</h1>
                <?php if($message == 'resolved'): ?>
                    <div style="background: #dcfce7; color: #166534; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">Uyuşmazlık karara bağlandı.</div>
                <?php endif; ?>
            </header>

            <div class="content-wrapper">
                <p style="color: #64748b; margin-bottom: 2rem;">Müşterilerin tasarım süreciyle ilgili itirazlarını buradan inceleyip karara bağlayabilirsiniz.</p>

                <?php if(empty($disputes)): ?>
                    <div class="empty-state">Hiç uyuşmazlık bulunmuyor.</div>
                <?php else: ?>
                    <?php foreach($disputes as $dispute): ?>
                    <div class="dispute-card" style="<?php echo $dispute['status'] !== 'pending' ? 'border-left-color: #cbd5e1; opacity: 0.7;' : ''; ?>">
                        <div class="dispute-header">
                            <div>
                                <h3 style="font-weight: 800;"><?php echo htmlspecialchars($dispute['customer_name']); ?> — Sipariş #<?php echo $dispute['order_id']; ?></h3>
                                <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.2rem;">Paket: <?php echo htmlspecialchars($dispute['package']); ?> | Tarih: <?php echo date('d.m.Y H:i', strtotime($dispute['created_at'])); ?></p>
                            </div>
                            <span class="badge status-badge <?php echo $dispute['status']; ?>">
                                <?php echo $dispute['status'] == 'pending' ? 'Bekleyen İtiraz' : 'Çözümlendi'; ?>
                            </span>
                        </div>
                        
                        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; font-size: 0.95rem; color: #475569;">
                            <strong>Müşteri İtiraz Notu:</strong><br>
                            <?php echo nl2br(htmlspecialchars($dispute['reason'])); ?>
                        </div>

                        <?php if($dispute['status'] == 'pending'): ?>
                        <div class="resolution-area">
                            <form action="../processes/admin_actions.php" method="POST">
                                <input type="hidden" name="action" value="resolve_dispute">
                                <input type="hidden" name="dispute_id" value="<?php echo $dispute['id']; ?>">
                                <input type="hidden" name="resolution" value="favor_customer">
                                <button type="submit" class="btn-resolve customer"><i data-lucide="check-circle" style="width: 14px; vertical-align: middle; margin-right: 0.5rem;"></i> Müşteriyi Haklı Bul (Revize İade Et)</button>
                            </form>
                            <form action="../processes/admin_actions.php" method="POST">
                                <input type="hidden" name="action" value="resolve_dispute">
                                <input type="hidden" name="dispute_id" value="<?php echo $dispute['id']; ?>">
                                <input type="hidden" name="resolution" value="favor_designer">
                                <button type="submit" class="btn-resolve designer">Tasarımcıyı Haklı Bul</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
