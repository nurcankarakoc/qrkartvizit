<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';

require_role_or_redirect($pdo, 'admin', '../auth/login.php');

$stmt_orders = $pdo->query("SELECT o.*, u.name as customer_name
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            ORDER BY o.created_at DESC");
$orders = $stmt_orders->fetchAll();

$status_map = [
    'pending' => 'Bekleyen',
    'pending_payment' => 'Ödeme Bekliyor',
    'pending_design' => 'Tasarım Havuzu',
    'designing' => 'Tasarımda',
    'awaiting_approval' => 'Müşteri Onayı',
    'revision_requested' => 'Revize İstendi',
    'approved' => 'Basıma Hazır',
    'printing' => 'Baskida',
    'shipping' => 'Kargoda',
    'completed' => 'Tamamlandı',
];

$status_options = array_keys($status_map);
$message_key = trim((string)($_GET['msg'] ?? ''));
$message_map = [
    'updated' => ['type' => 'success', 'text' => 'Sipariş durumu güncellendi.'],
    'invalid' => ['type' => 'error', 'text' => 'Geçersiz istek alındı.'],
    'error' => ['type' => 'error', 'text' => 'Sipariş güncellenirken bir hata oluştu.'],
];
$flash = $message_map[$message_key] ?? null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .badge { padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; }
        .status-badge.pending { background: #fef9c3; color: #a16207; }
        .status-badge.pending_payment { background: #fff7ed; color: #c2410c; }
        .status-badge.pending_design { background: #eff6ff; color: #1d4ed8; }
        .status-badge.designing { background: #eff6ff; color: var(--navy-blue); }
        .status-badge.awaiting_approval { background: #fff7ed; color: var(--gold); }
        .status-badge.revision_requested { background: #fff1f2; color: #be123c; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.printing { background: #e0f2fe; color: #0c4a6e; }
        .status-badge.shipping { background: #ede9fe; color: #5b21b6; }
        .status-badge.completed { background: #f1f5f9; color: #475569; }
        .status-form { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .status-select { min-width: 145px; padding: 0.45rem 0.6rem; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; font-size: 0.82rem; }
        .status-save-btn { border: none; border-radius: 8px; padding: 0.45rem 0.7rem; font-size: 0.75rem; font-weight: 700; cursor: pointer; background: var(--navy-blue); color: #fff; }
        .flash { margin-bottom: 1rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.9rem; font-weight: 700; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .table-container { overflow-x: auto; }
        @media (max-width: 768px) {
            .status-form { width: 100%; display: grid; gap: 0.55rem; }
            .status-select, .status-save-btn, .status-form .btn-action { width: 100%; }
            .status-form .btn-action { justify-content: center; }
        }
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
                    <li><a href="form-approvals.php"><i data-lucide="clipboard-check"></i> Form Onayları</a></li>
                    <li><a href="packages.php"><i data-lucide="package-2"></i> Paketler</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">A</div>
                    <div class="details"><span class="name">Super Admin</span><span class="role">Zerosoft</span></div>
                </div>
                <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1>Tüm Siparişler</h1>
            </header>

            <?php if ($flash): ?>
                <div class="flash <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <div class="content-wrapper">
                <section class="table-container">
                    <table class="data-table responsive-table">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Müşteri</th>
                                <th>Paket</th>
                                <th>Durum</th>
                                <th>Kalan Revize</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="7" class="empty-state">Kayıtlı sipariş bulunmuyor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $status = (string)($order['status'] ?? 'pending');
                                    $display_status = $status === 'disputed' ? 'revision_requested' : $status;
                                    ?>
                                    <tr>
                                        <td data-label="#ID"><strong>#<?php echo (int)$order['id']; ?></strong></td>
                                        <td data-label="Müşteri"><?php echo htmlspecialchars((string)$order['customer_name']); ?></td>
                                        <td data-label="Paket"><?php echo htmlspecialchars((string)($order['package'] ?? 'classic')); ?></td>
                                        <td data-label="Durum">
                                            <span class="badge status-badge <?php echo htmlspecialchars($display_status); ?>">
                                                <?php echo htmlspecialchars($status_map[$display_status] ?? $display_status); ?>
                                            </span>
                                        </td>
                                        <td data-label="Kalan Revize"><?php echo (int)($order['revision_count'] ?? 0); ?> Hak</td>
                                        <td data-label="Tarih"><?php echo date('d.m.Y', strtotime((string)$order['created_at'])); ?></td>
                                        <td data-label="İşlem" class="cell-actions">
                                            <form action="../processes/admin_actions.php" method="POST" class="status-form">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                <select name="new_status" class="status-select">
                                                    <?php foreach ($status_options as $option): ?>
                                                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $status ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($status_map[$option]); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="status-save-btn">Kaydet</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    
    <script>lucide.createIcons();</script>
</body>
</html>
