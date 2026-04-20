<?php
session_start();
require_once '../core/db.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

function format_order_datetime($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '-';
    }

    return date('d.m.Y H:i', $timestamp);
}

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Müşteri'];
$user_name = trim((string)($user['name'] ?? 'Müşteri'));
if ($user_name === '') {
    $user_name = 'Müşteri';
}

$has_created_at = table_has_column($pdo, 'orders', 'created_at');
$has_updated_at = table_has_column($pdo, 'orders', 'updated_at');

$select_parts = [];
$column_fallbacks = [
    'id' => '0',
    'package' => "''",
    'status' => "'pending'",
    'revision_count' => '0',
    'draft_path' => 'NULL',
];

foreach ($column_fallbacks as $column => $fallback_sql) {
    if (table_has_column($pdo, 'orders', $column)) {
        $select_parts[] = "`{$column}` AS `{$column}`";
    } else {
        $select_parts[] = "{$fallback_sql} AS `{$column}`";
    }
}

if ($has_created_at) {
    $select_parts[] = "`created_at` AS `created_at`";
} else {
    $select_parts[] = "NULL AS `created_at`";
}

if ($has_updated_at) {
    $select_parts[] = "`updated_at` AS `updated_at`";
} elseif ($has_created_at) {
    $select_parts[] = "`created_at` AS `updated_at`";
} else {
    $select_parts[] = "NULL AS `updated_at`";
}

$order_by_sql = $has_created_at ? '`created_at` DESC' : '`id` DESC';
$orders_sql = "SELECT " . implode(', ', $select_parts) . " FROM orders WHERE user_id = ? ORDER BY {$order_by_sql}";

$stmt = $pdo->prepare($orders_sql);
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll() ?: [];

$status_labels = [
    'pending' => 'Onay Bekliyor',
    'pending_payment' => 'Ödeme Bekleniyor',
    'pending_design' => 'Tasarım Planlandı',
    'designing' => 'Tasarım Aşamasında',
    'awaiting_approval' => 'Onayınız Bekleniyor',
    'revision_requested' => 'Revize İstendi',
    'approved' => 'Onaylandı / Baskıda',
    'printing' => 'Baskıda',
    'shipping' => 'Kargoda',
    'completed' => 'Tamamlandı',
    'disputed' => 'İtirazda',
];
$package_labels = [
    'classic' => 'Klasik Paket',
    'panel' => 'Sadece Panel',
    'smart' => '100 Baskı + 1 Aylık Abonelik',
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişlerim - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .orders-wrap { display: grid; gap: 1rem; }
        .order-card {
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            border: 1px solid transparent;
            border-radius: 18px;
            padding: 1.2rem;
            box-shadow: 0 12px 26px rgba(10, 47, 47, 0.05);
            transition: 0.25s ease;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 34px rgba(166, 128, 63, 0.16);
        }
        .order-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 0.8rem; }
        .order-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; margin-top: 0.8rem; }
        .meta-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.7rem 0.8rem; }
        .meta-k { font-size: 0.78rem; font-weight: 700; color: #64748b; margin-bottom: 0.25rem; }
        .meta-v { font-size: 0.9rem; font-weight: 700; color: #0f172a; }
        .order-actions { display: flex; gap: 0.65rem; flex-wrap: wrap; margin-top: 0.9rem; }
        .btn-lite {
            border: 1px solid transparent;
            background:
                linear-gradient(#f8fafc, #f8fafc) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #0f172a;
            border-radius: 10px;
            padding: 0.55rem 0.9rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: 0.25s ease;
        }
        .btn-lite:hover {
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(166, 128, 63, 0.2);
        }
        .status-badge { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.4rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-pending_payment { background: #ffedd5; color: #9a3412; }
        .status-pending_design { background: #eef2ff; color: #3730a3; }
        .status-designing { background: #dbeafe; color: #1d4ed8; }
        .status-awaiting_approval { background: #fff7ed; color: #c2410c; }
        .status-revision_requested { background: #ffe4e6; color: #be123c; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-printing { background: #cffafe; color: #155e75; }
        .status-shipping { background: #ede9fe; color: #5b21b6; }
        .status-completed { background: #f1f5f9; color: #334155; }
        .status-disputed { background: #fee2e2; color: #b91c1c; }
        @media (max-width: 900px) {
            .order-meta { grid-template-columns: 1fr; }
            .order-head { flex-direction: column; }
        }
    </style>
</head>
<body class="dashboard-body">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>
                <li><a href="profile.php"><i data-lucide="user-cog"></i> Profilimi Düzenle</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li class="active"><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
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

    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Siparişlerim</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Tüm siparişlerinizi ve güncel durumlarını buradan takip edin.</p>
            </div>
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.7rem 1rem; font-weight: 800; color: #0A2F2F;">
                Toplam Sipariş: <?php echo count($orders); ?>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if (empty($orders)): ?>
                <div class="order-card" style="text-align: center; padding: 2rem;">
                    <i data-lucide="package-x" style="width: 52px; height: 52px; color: #94a3b8; margin-bottom: 0.8rem;"></i>
                    <h3 style="margin: 0 0 0.4rem; color: #0A2F2F;">Henüz siparişiniz bulunmuyor</h3>
                    <p style="color: #64748b; margin: 0 0 1rem;">Yeni sipariş başlatarak tasarım ve baskı sürecine geçebilirsiniz.</p>
                    <a href="../auth/register.php" class="btn-lite" style="display: inline-flex;">Yeni Sipariş Başlat</a>
                </div>
            <?php else: ?>
                <div class="orders-wrap">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $slug = strtolower(trim((string)($order['package'] ?? '')));
                        $status = strtolower(trim((string)($order['status'] ?? 'pending')));
                        $status_class = preg_replace('/[^a-z0-9_-]/', '', $status);
                        if ($status_class === '') {
                            $status_class = 'pending';
                        }
                        $package_label = $package_labels[$slug] ?? ($slug !== '' ? ucfirst($slug) : 'Belirsiz');
                        $status_label = $status_labels[$status] ?? 'Beklemede';
                        ?>
                        <article class="order-card">
                            <div class="order-head">
                                <div>
                                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 700;">Sipariş #<?php echo (int)$order['id']; ?></div>
                                    <h3 style="margin: 0.2rem 0 0; color: #0A2F2F;"><?php echo htmlspecialchars($package_label); ?></h3>
                                </div>
                                <span class="status-badge status-<?php echo htmlspecialchars($status_class); ?>">
                                    <i data-lucide="clock" style="width: 14px;"></i>
                                    <?php echo htmlspecialchars($status_label); ?>
                                </span>
                            </div>

                            <div class="order-meta">
                                <div class="meta-box">
                                    <div class="meta-k">Kalan Revize</div>
                                    <div class="meta-v"><?php echo (int)($order['revision_count'] ?? 0); ?> hak</div>
                                </div>
                                <div class="meta-box">
                                    <div class="meta-k">Oluşturma</div>
                                    <div class="meta-v"><?php echo htmlspecialchars(format_order_datetime($order['created_at'] ?? null)); ?></div>
                                </div>
                                <div class="meta-box">
                                    <div class="meta-k">Güncelleme</div>
                                    <div class="meta-v"><?php echo htmlspecialchars(format_order_datetime($order['updated_at'] ?? null)); ?></div>
                                </div>
                            </div>

                            <div class="order-actions">
                                <a class="btn-lite" href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Sürecine Git</a>
                                <a class="btn-lite" href="profile.php"><i data-lucide="user-cog"></i> Profili Düzenle</a>
                                <?php if (!empty($order['draft_path'])): ?>
                                    <a class="btn-lite" href="../<?php echo htmlspecialchars((string)$order['draft_path']); ?>" target="_blank" rel="noopener">
                                        <i data-lucide="image"></i> Son Taslağı Aç
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
