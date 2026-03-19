<?php
session_start();
require_once '../core/db.php';

// Designer check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    header("Location: ../auth/login.php");
    exit();
}

$designer_id = $_SESSION['user_id'];

// New Orders pool
$stmt_new = $pdo->prepare("SELECT o.*, u.name as customer_name 
                           FROM orders o 
                           JOIN users u ON o.user_id = u.id
                           WHERE o.status IN ('pending', 'pending_design') 
                           ORDER BY o.created_at DESC");
$stmt_new->execute();
$new_orders = $stmt_new->fetchAll();

// Active Jobs: status = 'designing' or 'revision_requested' (some status might not exist yet, so we assume typical workflow)
$stmt_active = $pdo->prepare("SELECT o.*, u.name as customer_name 
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id
                              WHERE o.status IN ('designing', 'revision_requested', 'awaiting_approval') 
                              ORDER BY o.created_at DESC");
$stmt_active->execute();
$active_orders = $stmt_active->fetchAll();

// Completed/Approved: status = 'approved'
$stmt_completed = $pdo->prepare("SELECT o.*, u.name as customer_name 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id
                                 WHERE o.status IN ('approved', 'completed') 
                                 ORDER BY o.created_at DESC LIMIT 10");
$stmt_completed->execute();
$completed_orders = $stmt_completed->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarımcı Paneli — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-logotype">
                    <div class="mock-logo">Z</div>
                    <span>Zerosoft <small>Designer</small></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Panel</a></li>
                    <li><a href="designs.php"><i data-lucide="image"></i> Tasarimlarim</a></li>
                    <li><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
                    <li><a href="#"><i data-lucide="settings"></i> Ayarlar</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></div>
                    <div class="details">
                        <span class="name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                        <span class="role">Tasarımcı</span>
                    </div>
                </div>
                <a href="../processes/logout.php" class="logout-btn"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="header-search">
                    <i data-lucide="search"></i>
                    <input type="text" placeholder="Sipariş veya müşteri ara...">
                </div>
                <div class="header-actions">
                    <div class="notification-btn">
                        <i data-lucide="bell"></i>
                        <span class="dot"></span>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="welcome-section">
                    <h1>Hoş Geldin, <?php echo explode(' ', $_SESSION['user_name'] ?? 'Dostum')[0]; ?>! 👋</h1>
                    <p>Bugün tasarım bekleyen <strong><?php echo count($new_orders); ?> yeni sipariş</strong> var.</p>
                </div>

                <div class="stats-grid-dashboard">
                    <div class="stat-card">
                        <div class="stat-icon new"><i data-lucide="plus-circle"></i></div>
                        <div class="stat-info">
                            <span class="label">Yeni Sipariş</span>
                            <span class="value"><?php echo count($new_orders); ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon active"><i data-lucide="clock"></i></div>
                        <div class="stat-info">
                            <span class="label">Aktif İşler</span>
                            <span class="value"><?php echo count($active_orders); ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon completed"><i data-lucide="check-circle-2"></i></div>
                        <div class="stat-info">
                            <span class="label">Onaylanan</span>
                            <span class="value"><?php echo count($completed_orders); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-tables">
                    <!-- New Orders Table -->
                    <section class="table-container">
                        <div class="table-header">
                            <h2>Yeni Sipariş Havuzu</h2>
                            <a href="designs.php?filter=pending" class="view-all">Tumunu Gor</a>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Paket</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($new_orders)): ?>
                                    <tr><td colspan="5" class="empty-state">Henüz yeni sipariş yok.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($new_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <div class="customer-cell">
                                                    <div class="initials"><?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?></div>
                                                    <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge package-<?php echo strtolower($order['package'] ?? 'classic'); ?>"><?php echo htmlspecialchars($order['package'] ?? 'Classic'); ?></span></td>
                                            <td><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                            <td><span class="status-dot pending"></span> Yeni Sipariş</td>
                                            <td><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-action">İncele</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>

                    <!-- Active Jobs Table -->
                    <section class="table-container">
                        <div class="table-header">
                            <h2>Üzerimdeki İşler</h2>
                            <a href="designs.php" class="view-all">Tumunu Gor</a>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Durum</th>
                                    <th>Revize</th>
                                    <th>Kalan Hak</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_orders)): ?>
                                    <tr><td colspan="5" class="empty-state">Şu an aktif bir işiniz bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($active_orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td>
                                                <?php if($order['status'] == 'revision_requested'): ?>
                                                    <span class="status-chip revision">Revize İstendi</span>
                                                <?php elseif($order['status'] == 'awaiting_approval'): ?>
                                                    <span class="status-chip pending">Onay Bekliyor</span>
                                                <?php else: ?>
                                                    <span class="status-chip designing">Tasarım Yapılıyor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $order['package'] ?? 'Klasik'; ?></td>
                                            <td><?php echo $order['revision_count'] ?? 0; ?></td>
                                            <td><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-action primary">İncele/Yükle</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <style>
        .badge.package-classic { background: #f1f5f9; color: #475569; }
        .badge.package-panel { background: #ecfdf5; color: #065f46; }
        .badge.package-smart { background: #fff7ed; color: #9a3412; }
    </style>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
