<?php
session_start();
require_once '../core/db.php';

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

function table_exists(PDO $pdo, string $table): bool
{
    $table_escaped = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
    return (bool)$stmt->fetchColumn();
}

$has_order_package = table_has_column($pdo, 'orders', 'package');
$has_payments = table_exists($pdo, 'payments');
$has_payment_type = $has_payments && table_has_column($pdo, 'payments', 'type');
$has_payment_type_legacy = $has_payments && table_has_column($pdo, 'payments', 'payment_type');

$payment_type_col = '';
if ($has_payment_type) {
    $payment_type_col = 'type';
} elseif ($has_payment_type_legacy) {
    $payment_type_col = 'payment_type';
}

$payment_type_expr = $payment_type_col !== '' ? "p.`{$payment_type_col}`" : "NULL";
$order_amount_case = $payment_type_col !== ''
    ? "CASE WHEN {$payment_type_expr} = 'order' OR {$payment_type_expr} IS NULL THEN IFNULL(p.amount, 0) ELSE 0 END"
    : "IFNULL(p.amount, 0)";

if ($has_order_package) {
    $sales_sql = "SELECT COALESCE(o.package, 'classic') AS package,
                         COUNT(DISTINCT o.id) AS order_count,
                         COALESCE(SUM({$order_amount_case}), 0) AS order_total
                  FROM orders o
                  LEFT JOIN payments p ON o.id = p.order_id
                  GROUP BY COALESCE(o.package, 'classic')
                  ORDER BY order_count DESC";
} else {
    $sales_sql = "SELECT COALESCE(pk.slug, 'classic') AS package,
                         COUNT(DISTINCT o.id) AS order_count,
                         COALESCE(SUM({$order_amount_case}), 0) AS order_total
                  FROM orders o
                  LEFT JOIN packages pk ON pk.id = o.package_id
                  LEFT JOIN payments p ON o.id = p.order_id
                  GROUP BY COALESCE(pk.slug, 'classic')
                  ORDER BY order_count DESC";
}

if ($has_payments) {
    $stmt_sales = $pdo->prepare($sales_sql);
    $stmt_sales->execute();
    $package_stats = $stmt_sales->fetchAll();
} else {
    if ($has_order_package) {
        $stmt_sales = $pdo->query("SELECT COALESCE(package, 'classic') AS package, COUNT(*) AS order_count, 0 AS order_total FROM orders GROUP BY COALESCE(package, 'classic') ORDER BY order_count DESC");
    } else {
        $stmt_sales = $pdo->query("SELECT COALESCE(pk.slug, 'classic') AS package, COUNT(*) AS order_count, 0 AS order_total FROM orders o LEFT JOIN packages pk ON pk.id = o.package_id GROUP BY COALESCE(pk.slug, 'classic') ORDER BY order_count DESC");
    }
    $package_stats = $stmt_sales->fetchAll();
}

if ($payment_type_col !== '') {
    $stmt_extra_rev = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE {$payment_type_col} = 'extra_revision'");
    $extra_rev_total = (float)$stmt_extra_rev->fetchColumn();
} else {
    $extra_rev_total = 0.0;
}

$total_revenue = 0.0;
if ($has_payments) {
    $stmt_total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments");
    $total_revenue = (float)$stmt_total_revenue->fetchColumn();
}

$stmt_status = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$status_counts = $stmt_status->fetchAll(PDO::FETCH_KEY_PAIR);
$pending_count = (int)(($status_counts['pending'] ?? 0) + ($status_counts['pending_design'] ?? 0) + ($status_counts['pending_payment'] ?? 0));

$stmt_disputes = $pdo->query("SELECT d.*, u.name as customer_name, o.id as order_id
                              FROM disputes d
                              JOIN users u ON d.user_id = u.id
                              JOIN orders o ON d.order_id = o.id
                              WHERE d.status = 'pending'
                              ORDER BY d.created_at DESC");
$pending_disputes = $stmt_disputes->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Paneli - Zerosoft</title>
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
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">A</div>
                    <div class="details">
                        <span class="name">Sistem Yöneticisi</span>
                        <span class="role">Super Admin</span>
                    </div>
                </div>
                <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1>Sistem Genel Özeti</h1>
                <div class="header-actions">
                    <div class="date-badge" style="background: var(--white); padding: 0.6rem 1.2rem; border-radius: 14px; font-weight: 700; color: var(--navy-blue); border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.02);"><?php echo date('d.m.Y'); ?></div>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="stats-grid-dashboard" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                    <div class="stat-card" style="background: linear-gradient(135deg, var(--navy-dark), var(--navy-blue)); border: none;">
                        <div class="stat-info">
                            <span class="label" style="color: rgba(255,255,255,0.7); font-weight:600; font-size: 0.85rem; display:block; margin-bottom:0.5rem;">Toplam Ciro</span>
                            <span class="value" style="color: #fff; font-size: 1.8rem; font-weight: 800;"><?php echo number_format((float)$total_revenue, 2, ',', '.'); ?> TL</span>
                        </div>
                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--gold);">
                            <i data-lucide="wallet" style="width: 24px; height: 24px;"></i>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label" style="color: var(--text-muted); font-weight:600; font-size: 0.85rem; display:block; margin-bottom:0.5rem;">Ek Revize Geliri</span>
                            <span class="value" style="color: var(--navy-blue); font-size: 1.8rem; font-weight: 800;"><?php echo number_format($extra_rev_total, 2, ',', '.'); ?> TL</span>
                        </div>
                        <div style="width: 50px; height: 50px; background: rgba(166, 128, 63, 0.1); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--gold);">
                            <i data-lucide="refresh-cw" style="width: 24px; height: 24px;"></i>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label" style="color: var(--text-muted); font-weight:600; font-size: 0.85rem; display:block; margin-bottom:0.5rem;">Bekleyen Sipariş</span>
                            <span class="value" style="color: var(--navy-blue); font-size: 1.8rem; font-weight: 800;"><?php echo (int)$pending_count; ?></span>
                        </div>
                        <div style="width: 50px; height: 50px; background: rgba(10, 47, 47, 0.05); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--navy-blue);">
                            <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="label" style="color: var(--text-muted); font-weight:600; font-size: 0.85rem; display:block; margin-bottom:0.5rem;">Aktif Uyuşmazlık</span>
                            <span class="value" style="color: #ef4444; font-size: 1.8rem; font-weight: 800;"><?php echo count($pending_disputes); ?></span>
                        </div>
                        <div style="width: 50px; height: 50px; background: rgba(239, 68, 68, 0.05); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #ef4444;">
                            <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-tables" style="grid-template-columns: 1.5fr 1fr;">
                    <section class="table-container">
                        <div class="table-header"><h2>Paket Bazli Satislar</h2></div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Paket</th>
                                    <th>Satis Adedi</th>
                                    <th>Paket Geliri</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($package_stats)): ?>
                                    <tr><td colspan="3" class="empty-state">Satis kaydi bulunamadi.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($package_stats as $stat): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars((string)$stat['package']); ?></strong></td>
                                            <td><?php echo (int)$stat['order_count']; ?></td>
                                            <td><?php echo number_format((float)$stat['order_total'], 2, ',', '.'); ?> TL</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>

                    <section class="table-container">
                        <div class="table-header"><h2>Siparis Durumlari</h2></div>
                        <div style="padding: 1rem;">
                            <?php
                            $status_map = [
                                'pending' => 'Havuzda Bekleyen',
                                'pending_design' => 'Tasarim Havuzunda',
                                'pending_payment' => 'Odeme Bekliyor',
                                'designing' => 'Tasarlaniyor',
                                'awaiting_approval' => 'Onay Bekliyor',
                                'revision_requested' => 'Revize Surecinde',
                                'approved' => 'Basima Hazir',
                                'printing' => 'Baskida',
                                'shipping' => 'Kargoda',
                                'completed' => 'Tamamlanan',
                                'disputed' => 'Uyusmazlikta',
                            ];
                            foreach ($status_map as $key => $label):
                                $count = (int)($status_counts[$key] ?? 0);
                            ?>
                                <div style="display: flex; justify-content: space-between; padding: 0.8rem 0; border-bottom: 1px solid #f1f5f9;">
                                    <span><?php echo htmlspecialchars($label); ?></span>
                                    <span class="badge" style="background: #f1f5f9; color: #1e293b;"><?php echo $count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <div class="dashboard-tables" style="margin-top: 2rem;">
                    <section class="table-container">
                        <div class="table-header"><h2>Aktif Uyusmazliklar</h2></div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Musteri</th>
                                    <th>Itiraz</th>
                                    <th>Tarih</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_disputes)): ?>
                                    <tr><td colspan="4" class="empty-state">Aktif uyusmazlik bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_disputes as $dispute): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$dispute['customer_name']); ?></td>
                                            <td><span style="font-size: 0.85rem; color: #64748b;"><?php echo htmlspecialchars(substr((string)$dispute['reason'], 0, 80)); ?>...</span></td>
                                            <td><?php echo date('d.m.Y', strtotime((string)$dispute['created_at'])); ?></td>
                                            <td><a href="disputes.php" class="btn-action primary">Incele</a></td>
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

    <script src="../assets/js/dashboard-mobile.js"></script>
    
    <script>lucide.createIcons();</script>
</body>
</html>
