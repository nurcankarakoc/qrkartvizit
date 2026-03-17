<?php
session_start();
require_once '../core/db.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

$has_order_package = table_has_column($pdo, 'orders', 'package');
$has_payment_type = table_has_column($pdo, 'payments', 'type');
$payment_type_col = $has_payment_type ? 'type' : 'payment_type';

// 1. Finance Stats
$sales_sql = $has_order_package
    ? "SELECT COALESCE(o.package, 'classic') AS package, COUNT(*) as count, SUM(IFNULL(p.amount, 0)) as total
       FROM orders o
       LEFT JOIN payments p ON o.id = p.order_id
       GROUP BY COALESCE(o.package, 'classic')"
    : "SELECT COALESCE(pk.slug, 'classic') AS package, COUNT(*) as count, SUM(IFNULL(p.amount, 0)) as total
       FROM orders o
       LEFT JOIN packages pk ON pk.id = o.package_id
       LEFT JOIN payments p ON o.id = p.order_id
       GROUP BY COALESCE(pk.slug, 'classic')";

$stmt_sales = $pdo->query($sales_sql);
$package_stats = $stmt_sales->fetchAll();

$stmt_extra_rev = $pdo->query("SELECT SUM(amount) FROM payments WHERE {$payment_type_col} = 'extra_revision'");
$extra_rev_total = $stmt_extra_rev->fetchColumn() ?: 0;

$stmt_total_revenue = $pdo->query("SELECT SUM(amount) FROM payments");
$total_revenue = $stmt_total_revenue->fetchColumn() ?: 0;

// 2. Order Status Summary
$stmt_status = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$status_counts = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);
$pending_count = ($status_counts['pending'] ?? 0) + ($status_counts['pending_design'] ?? 0) + ($status_counts['pending_payment'] ?? 0);

// 3. Pending Disputes
$stmt_disputes = $pdo->query("SELECT d.*, u.name as customer_name, o.id as order_id 
                              FROM disputes d 
                              JOIN users u ON d.user_id = u.id 
                              JOIN orders o ON d.order_id = o.id 
                              WHERE d.status = 'pending'");
$pending_disputes = $stmt_disputes->fetchAll();

// 4. Recent Orders
$stmt_recent = $pdo->query("SELECT o.*, u.name as customer_name FROM orders o 
                            JOIN users u ON o.user_id = u.id 
                            ORDER BY o.created_at DESC LIMIT 5");
$recent_orders = $stmt_recent->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Süper Admin Paneli — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-logotype">
                    <div class="mock-logo">Z</div>
                    <span>Zerosoft <small>Admin</small></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>
                    <li><a href="orders.php"><i data-lucide="shopping-cart"></i> Tüm Siparişler</a></li>
                    <li><a href="designers.php"><i data-lucide="users"></i> Tasarımcı Yönetimi</a></li>
                    <li><a href="disputes.php"><i data-lucide="alert-circle"></i> Uyuşmazlıklar</a></li>
                    <li><a href="#"><i data-lucide="settings"></i> Sistem Ayarları</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">A</div>
                    <div class="details">
                        <span class="name">Süper Admin</span>
                        <span class="role">Zerosoft Yönetici</span>
                    </div>
                </div>
                <a href="../processes/logout.php" class="logout-btn" style="color: rgba(255,255,255,0.4);"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1>Sistem Genel Özeti</h1>
                <div class="header-actions">
                    <div class="date-badge" style="background: var(--white); padding: 0.5rem 1rem; border-radius: 10px; font-weight: 600; color: var(--navy-blue); border: 1px solid #e2e8f0;"><?php echo date('d.m.Y'); ?></div>
                </div>
            </header>

            <div class="content-wrapper">
                <!-- Finance Overview -->
                <div class="stats-grid-dashboard">
                    <div class="stat-card" style="background: linear-gradient(135deg, var(--navy-dark), var(--navy-blue)); color: #fff;">
                        <div class="stat-info">
                            <span class="label" style="color: rgba(255,255,255,0.8);">Toplam Ciro</span>
                            <span class="value" style="color: #fff;"><?php echo number_format($total_revenue, 2, ',', '.'); ?> ₺</span>
                        </div>
                        <i data-lucide="wallet" style="opacity: 0.3; width: 48px; height: 48px;"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label">Ek Revize Geliri</span>
                            <span class="value"><?php echo number_format($extra_rev_total, 2, ',', '.'); ?> ₺</span>
                        </div>
                        <i data-lucide="refresh-cw" style="color: var(--gold); opacity: 0.5;"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label">Bekleyen Sipariş</span>
                            <span class="value"><?php echo $pending_count; ?></span>
                        </div>
                        <i data-lucide="clock" style="color: var(--navy-blue); opacity: 0.3;"></i>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label">Aktif Uyuşmazlık</span>
                            <span class="value" style="color: #ef4444;"><?php echo count($pending_disputes); ?></span>
                        </div>
                        <i data-lucide="alert-triangle" style="color: #ef4444; opacity: 0.3;"></i>
                    </div>
                </div>

                <div class="dashboard-tables" style="grid-template-columns: 1.5fr 1fr;">
                    <!-- Sales by Package -->
                    <section class="table-container">
                        <div class="table-header"><h2>Paket Bazlı Satışlar</h2></div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Paket İsmi</th>
                                    <th>Adet</th>
                                    <th>Toplam Kazanç</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($package_stats as $stat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($stat['package'] ?: 'Klasik Paket'); ?></strong></td>
                                    <td><?php echo $stat['count']; ?></td>
                                    <td><?php echo number_format($stat['total'] ?: 0, 2, ',', '.'); ?> ₺</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>

                    <!-- Status Distribution -->
                    <section class="table-container">
                        <div class="table-header"><h2>Sipariş Durumları</h2></div>
                        <div style="padding: 1rem;">
                            <?php 
                            $status_map = [
                                'pending' => 'Havuzda Bekleyen',
                                'pending_design' => 'Tasarım Havuzunda',
                                'pending_payment' => 'Ödeme Bekliyor',
                                'designing' => 'Tasarlanıyor',
                                'awaiting_approval' => 'Onay Bekliyor',
                                'revision_requested' => 'Revize Sürecinde',
                                'approved' => 'Basıma Hazır',
                                'completed' => 'Tamamlanan'
                            ];
                            foreach($status_map as $key => $label): 
                                $count = $status_counts[$key] ?? 0;
                            ?>
                            <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9;">
                                <span><?php echo $label; ?></span>
                                <span class="badge" style="background: #f1f5f9; color: #1e293b;"><?php echo $count; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <!-- Disputes and Recent Orders -->
                <div class="dashboard-tables" style="margin-top: 2rem;">
                    <section class="table-container">
                        <div class="table-header"><h2>Aktif Uyuşmazlıklar</h2></div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Hata Bildirimi</th>
                                    <th>Tarih</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($pending_disputes)): ?>
                                    <tr><td colspan="4" class="empty-state">Hiç aktif uyuşmazlık bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach($pending_disputes as $dispute): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dispute['customer_name']); ?></td>
                                        <td><span style="font-size: 0.85rem; color: #64748b;"><?php echo substr($dispute['reason'], 0, 50); ?>...</span></td>
                                        <td><?php echo date('d.m.Y', strtotime($dispute['created_at'])); ?></td>
                                        <td><a href="disputes.php?id=<?php echo $dispute['id']; ?>" class="btn-action primary">Çözümle</a></td>
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

    <script>lucide.createIcons();</script>
</body>
</html>
