<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/dynamic_form.php';

require_role_or_redirect($pdo, 'admin', '../auth/login.php');

df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

$pending_stmt = $pdo->query(
    "SELECT r.*, u.name AS requester_name
     FROM form_change_requests r
     LEFT JOIN users u ON u.id = r.requested_by
     WHERE r.status = 'pending'
     ORDER BY r.created_at ASC"
);
$pending_requests = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_stmt = $pdo->query(
    "SELECT r.*, u.name AS requester_name, a.name AS reviewer_name
     FROM form_change_requests r
     LEFT JOIN users u ON u.id = r.requested_by
     LEFT JOIN users a ON a.id = r.reviewed_by
     WHERE r.status IN ('approved', 'rejected')
     ORDER BY r.reviewed_at DESC, r.created_at DESC
     LIMIT 40"
);
$recent_requests = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

$msg_key = trim((string) ($_GET['msg'] ?? ''));
$msg_map = [
    'csrf' => ['type' => 'error', 'text' => 'Güvenlik doğrulaması başarısız oldu.'],
    'approved' => ['type' => 'success', 'text' => 'Talep onaylandı ve sisteme uygulandı.'],
    'rejected' => ['type' => 'success', 'text' => 'Talep reddedildi.'],
    'invalid' => ['type' => 'error', 'text' => 'Geçersiz işlem.'],
    'error' => ['type' => 'error', 'text' => 'İşlem sırasında beklenmeyen bir hata oluştu.'],
];
$flash = $msg_map[$msg_key] ?? null;

function req_status_label_admin(string $status): string
{
    return match ($status) {
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
        default => 'Bekliyor',
    };
}

function req_status_class_admin(string $status): string
{
    return match ($status) {
        'approved' => 'req-approved',
        'rejected' => 'req-rejected',
        default => 'req-pending',
    };
}

function req_type_label_admin(string $type): string
{
    return match ($type) {
        'field_create' => 'Yeni Alan',
        'option_create' => 'Yeni Seçenek',
        default => 'Diğer Talep',
    };
}

function field_type_label_admin(string $field_type): string
{
    return match ($field_type) {
        'text' => 'Kısa Metin',
        'textarea' => 'Uzun Metin',
        'select' => 'Açılır Liste',
        'email' => 'E-posta',
        'url' => 'Web Adresi',
        'tel' => 'Telefon',
        'number' => 'Sayı',
        default => 'Metin',
    };
}

function build_request_summary_admin(array $request): array
{
    $summary = [];
    $type = (string) ($request['request_type'] ?? '');
    $payload_raw = (string) ($request['payload_json'] ?? '');
    $payload = json_decode($payload_raw, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    if ($type === 'field_create') {
        $label = trim((string) ($payload['field_label'] ?? ''));
        $field_type = trim((string) ($payload['field_type'] ?? ''));
        $placeholder = trim((string) ($payload['placeholder'] ?? ''));
        $default_value = trim((string) ($payload['default_value'] ?? ''));
        $show_packages = trim((string) ($payload['show_on_packages'] ?? ''));
        $required_packages = trim((string) ($payload['required_on_packages'] ?? ''));
        $is_required = (int) ($payload['is_required'] ?? 0) === 1;

        if ($label !== '') {
            $summary[] = 'Alan Adı: ' . $label;
        }
        if ($field_type !== '') {
            $summary[] = 'Alan Tipi: ' . field_type_label_admin($field_type);
        }
        if ($placeholder !== '') {
            $summary[] = 'Placeholder: ' . $placeholder;
        }
        if ($default_value !== '') {
            $summary[] = 'Varsayılan Değer: ' . $default_value;
        }
        if ($show_packages !== '') {
            $summary[] = 'Görüneceği Paketler: ' . $show_packages;
        }
        if ($required_packages !== '') {
            $summary[] = 'Zorunlu Paketler: ' . $required_packages;
        }
        if ($is_required) {
            $summary[] = 'Her durumda zorunlu';
        }
    } elseif ($type === 'option_create') {
        $label = trim((string) ($payload['option_label'] ?? ''));
        $code = trim((string) ($payload['option_value'] ?? ''));

        if ($label !== '') {
            $summary[] = 'Seçenek Adı: ' . $label;
        }
        if ($code !== '') {
            $summary[] = 'Seçenek Kodu: ' . $code;
        }
    }

    if ($summary === []) {
        $summary[] = 'Bu talep için ek detay yok.';
    }

    return $summary;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Onay Merkezi - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .flash { margin-bottom: 1rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.9rem; font-weight: 700; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1rem; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04); margin-bottom: 1rem; }
        .request-item { border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem; margin-bottom: 0.7rem; }
        .request-item:last-child { margin-bottom: 0; }
        .request-summary { margin: 0.55rem 0 0; padding-left: 1rem; color: #334155; font-size: 0.82rem; }
        .request-summary li { margin-bottom: 0.25rem; }
        .review-form { margin-top: 0.6rem; display: grid; gap: 0.5rem; }
        .review-note { width: 100%; border: 1px solid #dbe3ee; border-radius: 10px; padding: 0.6rem 0.7rem; font-size: 0.85rem; min-height: 70px; resize: vertical; }
        .btn-row { display: flex; gap: 0.55rem; flex-wrap: wrap; }
        .tiny-btn { border: 1px solid #dbe3ee; background: #fff; color: #0f172a; border-radius: 9px; padding: 0.5rem 0.75rem; font-size: 0.79rem; font-weight: 700; cursor: pointer; }
        .tiny-btn.primary { background: #0A2F2F; color: #fff; border-color: #0A2F2F; }
        .tiny-btn.warn { border-color: #fecaca; color: #991b1b; background: #fff; }
        .pill { border-radius: 999px; padding: 0.2rem 0.6rem; font-size: 0.72rem; font-weight: 800; display: inline-flex; }
        .req-pending { color: #9a3412; background: #fff7ed; border: 1px solid #fed7aa; }
        .req-approved { color: #166534; background: #ecfdf5; border: 1px solid #a7f3d0; }
        .req-rejected { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
        .history-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .history-table th, .history-table td { padding: 0.6rem; border-bottom: 1px solid #f1f5f9; text-align: left; font-size: 0.81rem; white-space: normal; word-break: break-word; }
        @media (max-width: 768px) {
            .btn-row .tiny-btn { width: 100%; min-height: 44px; }
            .history-table th, .history-table td { font-size: 0.78rem; padding: 0.45rem; }
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
                    <li><a href="orders.php"><i data-lucide="shopping-cart"></i> Tüm Siparişler</a></li>
                    <li class="active"><a href="form-approvals.php"><i data-lucide="clipboard-check"></i> Form Onayları</a></li>
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
                <div>
                    <h1 style="margin:0;">Form Onay Merkezi</h1>
                    <p style="margin:0.35rem 0 0; color:#64748b;">Tasarımcı taleplerini onayla veya reddet.</p>
                </div>
                <div class="date-badge" style="background:#fff; border:1px solid #e2e8f0; padding:0.55rem 0.75rem; border-radius:10px; font-weight:700;">
                    Bekleyen: <?php echo count($pending_requests); ?>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="flash <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <section class="card">
                <h2 style="margin:0 0 0.8rem; font-size:1.05rem;">Bekleyen Talepler</h2>
                <?php if (empty($pending_requests)): ?>
                    <div style="color:#64748b;">Bekleyen talep bulunmuyor.</div>
                <?php else: ?>
                    <?php foreach ($pending_requests as $request): ?>
                        <?php
                            $request_type = (string) ($request['request_type'] ?? '');
                            $summary_items = build_request_summary_admin($request);
                        ?>
                        <article class="request-item">
                            <div style="display:flex; justify-content:space-between; gap:0.6rem; align-items:flex-start;">
                                <div>
                                    <strong><?php echo htmlspecialchars(req_type_label_admin($request_type)); ?></strong>
                                    <div style="font-size:0.79rem; color:#64748b;">
                                        Talep Eden: <?php echo htmlspecialchars((string) ($request['requester_name'] ?? 'Bilinmiyor')); ?>
                                        | Tarih: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $request['created_at']))); ?>
                                    </div>
                                </div>
                                <span class="pill req-pending">Bekliyor</span>
                            </div>

                            <ul class="request-summary">
                                <?php foreach ($summary_items as $item): ?>
                                    <li><?php echo htmlspecialchars($item); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="btn-row" style="margin-top:0.6rem;">
                                <form action="../processes/admin_form_approval.php" method="POST" class="review-form" style="flex:1;">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <textarea name="review_note" class="review-note" placeholder="Opsiyonel admin notu"></textarea>
                                    <button type="submit" class="tiny-btn primary">Onayla ve Uygula</button>
                                </form>

                                <form action="../processes/admin_form_approval.php" method="POST" class="review-form" style="flex:1;">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                    <textarea name="review_note" class="review-note" placeholder="Red nedeni (opsiyonel)"></textarea>
                                    <button type="submit" class="tiny-btn warn">Reddet</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2 style="margin:0 0 0.8rem; font-size:1.05rem;">Son İşlenen Talepler</h2>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Tip</th>
                            <th>Durum</th>
                            <th>Tasarımcı</th>
                            <th>İnceleyen</th>
                            <th>İşlenme</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_requests)): ?>
                            <tr><td colspan="5" style="color:#64748b;">Henüz işlenmiş kayıt yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_requests as $request): ?>
                                <?php $status = (string) ($request['status'] ?? 'pending'); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(req_type_label_admin((string) ($request['request_type'] ?? ''))); ?></td>
                                    <td><span class="pill <?php echo req_status_class_admin($status); ?>"><?php echo htmlspecialchars(req_status_label_admin($status)); ?></span></td>
                                    <td><?php echo htmlspecialchars((string) ($request['requester_name'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($request['reviewer_name'] ?? '-')); ?></td>
                                    <td>
                                        <?php
                                            $reviewed_at = (string) ($request['reviewed_at'] ?? '');
                                            echo $reviewed_at !== '' ? htmlspecialchars(date('d.m.Y H:i', strtotime($reviewed_at))) : '-';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
