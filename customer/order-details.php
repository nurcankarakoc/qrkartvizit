<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/dynamic_form.php';
require_once '../core/customer_access.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$package_state = qrk_get_customer_package_state($pdo, $user_id);
$can_create_order = (bool)($package_state['can_create_order'] ?? false);
$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: orders.php');
    exit();
}

function order_details_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!df_table_exists($pdo, $table)) {
        return false;
    }
    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

function order_details_format_datetime($value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? '-' : date('d.m.Y H:i', $timestamp);
}

df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

$select_parts = [
    'o.id',
    'o.user_id',
    (order_details_has_column($pdo, 'orders', 'package') ? 'o.package' : "'' AS package"),
    (order_details_has_column($pdo, 'orders', 'status') ? 'o.status' : "'pending' AS status"),
    (order_details_has_column($pdo, 'orders', 'revision_count') ? 'o.revision_count' : '0 AS revision_count'),
    (order_details_has_column($pdo, 'orders', 'company_name') ? 'o.company_name' : "'' AS company_name"),
    (order_details_has_column($pdo, 'orders', 'job_title') ? 'o.job_title' : "'' AS job_title"),
    (order_details_has_column($pdo, 'orders', 'design_notes') ? 'o.design_notes' : "'' AS design_notes"),
    (order_details_has_column($pdo, 'orders', 'created_at') ? 'o.created_at' : 'NULL AS created_at'),
    (order_details_has_column($pdo, 'orders', 'updated_at') ? 'o.updated_at' : (order_details_has_column($pdo, 'orders', 'created_at') ? 'o.created_at AS updated_at' : 'NULL AS updated_at')),
];

$stmt = $pdo->prepare('SELECT ' . implode(', ', $select_parts) . ' FROM orders o WHERE o.id = ? AND o.user_id = ? LIMIT 1');
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

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
];

$package_labels = [
    'classic' => 'Klasik Paket',
    'panel' => 'Sadece Panel',
    'smart' => 'Akıllı Paket',
];

if (strtolower(trim((string)($order['status'] ?? 'pending'))) === 'disputed') {
    $order['status'] = 'revision_requested';
}

$order_answers = df_get_order_answers($pdo, $order_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .detail-grid { display: grid; grid-template-columns: 1.45fr 1fr; gap: 1rem; }
        .detail-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 1.15rem; box-shadow: 0 14px 28px rgba(10, 47, 47, 0.05); }
        .detail-title { margin: 0 0 0.85rem; color: #0A2F2F; font-size: 1.02rem; }
        .detail-info { display: grid; gap: 0.65rem; }
        .detail-row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.7rem 0; border-bottom: 1px solid #f1f5f9; }
        .detail-row:last-child { border-bottom: 0; }
        .detail-row span:first-child { color: #64748b; font-weight: 700; }
        .detail-row span:last-child { color: #0f172a; font-weight: 800; text-align: right; }
        .notes-box { white-space: pre-wrap; line-height: 1.7; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 1rem; }
        .answers-list { display: grid; gap: 0.7rem; }
        .answers-item { border: 1px solid #e2e8f0; border-radius: 16px; padding: 0.85rem 0.95rem; background: #fbfdff; }
        .answers-label { font-size: 0.76rem; font-weight: 800; text-transform: uppercase; color: #64748b; }
        .answers-value { margin-top: 0.3rem; white-space: pre-wrap; color: #0f172a; line-height: 1.6; }
        @media (max-width: 960px) { 
            .detail-grid { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; align-items: stretch !important; gap: 1rem; padding: 1.25rem 1rem !important; margin-bottom: 2rem; }
            .top-bar .btn-action { width: 100%; justify-content: center; }
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
                <li><a href="new-order.php"><i data-lucide="plus-circle"></i> Yeni Sipariş</a></li>
                <li><a href="profile.php"><i data-lucide="user-cog"></i> Profilimi Düzenle</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li class="active"><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <?php if (!$can_create_order): ?>
                <a href="profile.php" class="btn-action" style="background:#fff; color:#0A2F2F; border:1px solid #dbe4ef;"><i data-lucide="globe"></i> Web Sitesini Güncelle</a>
            <?php endif; ?>
            <a href="orders.php" class="btn-action"><i data-lucide="arrow-left"></i> Siparişlerime Dön</a>
            <div style="font-weight:900; color:#0A2F2F;">Sipariş #<?php echo (int)$order['id']; ?></div>
        </header>

        <div class="content-wrapper">
            <div class="detail-grid">
                <section class="detail-card">
                    <h2 class="detail-title">Talep Özeti</h2>
                    <div class="detail-info">
                        <div class="detail-row">
                            <span>Paket</span>
                            <span><?php echo htmlspecialchars($package_labels[strtolower((string)($order['package'] ?? ''))] ?? (string)($order['package'] ?? '-')); ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Durum</span>
                            <span><?php echo htmlspecialchars($status_labels[strtolower((string)($order['status'] ?? 'pending'))] ?? (string)($order['status'] ?? '-')); ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Kalan Revize</span>
                            <span><?php echo (int)($order['revision_count'] ?? 0); ?> hak</span>
                        </div>
                        <div class="detail-row">
                            <span>Şirket</span>
                            <span><?php echo htmlspecialchars((string)($order['company_name'] ?? '-')); ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Unvan</span>
                            <span><?php echo htmlspecialchars((string)($order['job_title'] ?? '-')); ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Oluşturma</span>
                            <span><?php echo htmlspecialchars(order_details_format_datetime($order['created_at'] ?? null)); ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Güncelleme</span>
                            <span><?php echo htmlspecialchars(order_details_format_datetime($order['updated_at'] ?? null)); ?></span>
                        </div>
                    </div>

                    <h3 class="detail-title" style="margin-top:1.2rem;">Tasarım Notları</h3>
                    <div class="notes-box"><?php echo htmlspecialchars(trim((string)($order['design_notes'] ?? '')) !== '' ? (string)$order['design_notes'] : 'Bu sipariş için ayrıca not girilmemiş.'); ?></div>
                </section>

                <section class="detail-card">
                    <h2 class="detail-title">Form Cevapları</h2>
                    <?php if ($order_answers === []): ?>
                        <div class="notes-box">Bu siparişte kayıtlı form cevabı bulunmuyor.</div>
                    <?php else: ?>
                        <div class="answers-list">
                            <?php foreach ($order_answers as $answer): ?>
                                <div class="answers-item">
                                    <div class="answers-label"><?php echo htmlspecialchars((string)($answer['field_label'] ?? $answer['field_key'] ?? 'Alan')); ?></div>
                                    <div class="answers-value"><?php echo htmlspecialchars((string)($answer['value_text'] ?? '')); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
        const CAN_CREATE_ORDER = <?php echo $can_create_order ? 'true' : 'false'; ?>;
        if (!CAN_CREATE_ORDER) {
            document.querySelectorAll('a[href="new-order.php"]').forEach((link) => {
                link.setAttribute('href', 'profile.php');
                link.innerHTML = '<i data-lucide="globe"></i> Web Sitesini Güncelle';
            });
            lucide.createIcons();
        }
    </script>
</body>
</html>
