<?php
session_start();
require_once '../core/db.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all orders with customer names
$stmt_orders = $pdo->query("SELECT o.*, u.name as customer_name 
                            FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            ORDER BY o.created_at DESC");
$orders = $stmt_orders->fetchAll();

$status_map = [
    'pending' => 'Yönetici Onayı Bekliyor',
    'designing' => 'Tasarım Aşamasında',
    'awaiting_approval' => 'Müşteri Onayı Bekliyor',
    'revision_requested' => 'Revize Sürecinde',
    'approved' => 'Basıma Hazır',
    'completed' => 'Tamamlanan'
];

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .badge { padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
        .status-badge.pending { background: #fef9c3; color: #a16207; }
        .status-badge.designing { background: #eff6ff; color: #1d4ed8; }
        .status-badge.awaiting_approval { background: #fef2f2; color: #b91c1c; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.completed { background: #f0fdf4; color: #16a34a; }
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
                    <li class="active"><a href="orders.php"><i data-lucide="shopping-cart"></i> Tüm Siparişler</a></li>
                    <li><a href="designers.php"><i data-lucide="users"></i> Tasarımcı Yönetimi</a></li>
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
                <h1>Tüm Siparişler</h1>
                <div class="header-search">
                    <i data-lucide="search"></i>
                    <input type="text" placeholder="Müşteri veya paket ara...">
                </div>
            </header>

            <div class="content-wrapper">
                <section class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Müşteri</th>
                                <th>Paket</th>
                                <th>Durum</th>
                                <th>Revize</th>
                                <th>Tarih</th>
                                <th>Aksiyon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['package']); ?></td>
                                <td>
                                    <span class="badge status-badge <?php echo $order['status']; ?>">
                                        <?php echo $status_map[$order['status']] ?? $order['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $order['revision_count']; ?> Hak</td>
                                <td><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                <td><a href="#" class="btn-action">Detay</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
