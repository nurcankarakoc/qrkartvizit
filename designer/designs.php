<?php
session_start();
require_once '../core/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    header("Location: ../auth/login.php");
    exit();
}

$designer_id = (int)($_SESSION['user_id'] ?? 0);
$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
$allowed_filters = ['all', 'pending', 'approved', 'revision_requested', 'rejected'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$base_sql = "SELECT d.*, o.package, o.status AS order_status, u.name AS customer_name
             FROM design_drafts d
             JOIN orders o ON o.id = d.order_id
             JOIN users u ON u.id = o.user_id
             WHERE d.designer_id = ?";
$params = [$designer_id];

if ($filter === 'approved') {
    $base_sql .= " AND d.id = (
                        SELECT dd.id
                        FROM design_drafts dd
                        WHERE dd.order_id = d.order_id
                          AND dd.designer_id = d.designer_id
                        ORDER BY dd.created_at DESC, dd.id DESC
                        LIMIT 1
                   )
                   AND (d.status = 'approved' OR o.status IN ('approved', 'completed'))";
} elseif ($filter !== 'all') {
    $base_sql .= " AND d.status = ?";
    $params[] = $filter;
}

$base_sql .= " ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($base_sql);
$stmt->execute($params);
$drafts = $stmt->fetchAll();

$stmt_count = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM design_drafts WHERE designer_id = ? GROUP BY status");
$stmt_count->execute([$designer_id]);
$count_rows = $stmt_count->fetchAll(PDO::FETCH_KEY_PAIR);
$count_all = array_sum(array_map('intval', $count_rows));

$stmt_approved_count = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT d.order_id
        FROM design_drafts d
        JOIN orders o ON o.id = d.order_id
        WHERE d.designer_id = ?
          AND d.id = (
                SELECT dd.id
                FROM design_drafts dd
                WHERE dd.order_id = d.order_id
                  AND dd.designer_id = d.designer_id
                ORDER BY dd.created_at DESC, dd.id DESC
                LIMIT 1
          )
          AND (d.status = 'approved' OR o.status IN ('approved', 'completed'))
        GROUP BY d.order_id
    ) approved_orders"
);
$stmt_approved_count->execute([$designer_id]);
$approved_total_count = (int)$stmt_approved_count->fetchColumn();

function status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Onaylandı',
        'revision_requested' => 'Revize İstendi',
        'rejected' => 'Reddedildi',
        default => 'Beklemede',
    };
}

function status_chip_class(string $status): string
{
    return match ($status) {
        'approved' => 'chip-approved',
        'revision_requested' => 'chip-revision',
        'rejected' => 'chip-rejected',
        default => 'chip-pending',
    };
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarımlarım - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .filter-row { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .filter-link { text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 999px; padding: 0.45rem 0.85rem; font-size: 0.82rem; font-weight: 700; transition: var(--transition-smooth); }
        .filter-link.active { background: var(--gold); color: #fff; border-color: var(--gold); box-shadow: 0 4px 12px rgba(166,128,63,0.2); }
        .filter-link:hover:not(.active) { background: #fff; border-color: var(--gold); color: var(--gold); transform: translateY(-3px); }
        
        .design-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .design-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(10px); 
            -webkit-backdrop-filter: blur(10px); 
            border: 2px solid rgba(166, 128, 63, 0.3); 
            border-radius: 20px; 
            overflow: hidden; 
            box-shadow: 0 12px 35px rgba(10, 47, 47, 0.06); 
            display: flex; 
            flex-direction: column; 
            transition: var(--transition-smooth);
        }
        .design-card:hover { transform: translateY(-5px); box-shadow: 0 18px 45px rgba(10, 47, 47, 0.12); border-color: var(--gold); }
        
        .design-preview { height: 220px; background: #f8fafc; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #e2e8f0; position: relative; }
        .design-preview img { 
            max-width: 92%; 
            max-height: 92%; 
            object-fit: contain; 
            border: 1.5px solid rgba(166, 128, 63, 0.3); 
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: var(--transition-smooth);
            background: #fff;
        }
        .design-card:hover .design-preview img {
            border-color: var(--gold);
            transform: scale(1.03);
            box-shadow: 0 12px 30px rgba(166, 128, 63, 0.2);
        }

        .design-meta { padding: 1.25rem; display: grid; gap: 0.6rem; }
        .design-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
        
        .chip { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.25rem 0.75rem; font-size: 0.75rem; font-weight: 700; }
        .chip-pending { background: #eff6ff; color: #1d4ed8; }
        .chip-approved { background: #dcfce7; color: #166534; }
        .chip-revision { background: #fff1f2; color: #be123c; }
        .chip-rejected { background: #fef2f2; color: #991b1b; }
        
        .mini-btn { text-decoration: none; border: 1px solid #e2e8f0; background: #fff; color: #1e293b; border-radius: 12px; padding: 0.5rem 0.85rem; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; transition: var(--transition-smooth); }
        .mini-btn:hover { border-color: var(--gold); color: var(--gold); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(166,128,63,0.1); }
        .mini-btn.primary { background: var(--navy-blue); color: #fff; border-color: var(--navy-blue); }
        .mini-btn.primary:hover { background: var(--navy-dark); color: #fff; }

        @media (max-width: 768px) {
            .mini-btn { min-height: 44px; flex: 1; justify-content: center; }
            .design-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="brand-logotype" style="text-decoration: none;">
                    <div class="mock-logo">Z</div>
                    <span>Zerosoft <small>Designer</small></span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Panel</a></li>
                    <li class="<?php echo ($filter !== 'approved') ? 'active' : ''; ?>"><a href="designs.php"><i data-lucide="image"></i> Tasarımlarım</a></li>
                    <li class="<?php echo ($filter === 'approved') ? 'active' : ''; ?>"><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
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
                <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <div>
                    <h1 style="margin:0;">Tasarımlarım</h1>
                    <p style="margin:0.35rem 0 0; color:#64748b;">Yüklediğiniz tüm taslaklar bu ekranda listelenir.</p>
                </div>
                <div class="date-badge" style="background:#fff; border:1px solid #e2e8f0; padding:0.5rem 0.8rem; border-radius:10px; font-weight:700;">
                    Toplam: <?php echo (int)$count_all; ?>
                </div>
            </header>

            <div class="filter-row">
                <a href="designs.php?filter=all" class="filter-link <?php echo $filter === 'all' ? 'active' : ''; ?>">Tümü (<?php echo (int)$count_all; ?>)</a>
                <a href="designs.php?filter=pending" class="filter-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">Beklemede (<?php echo (int)($count_rows['pending'] ?? 0); ?>)</a>
                <a href="designs.php?filter=approved" class="filter-link <?php echo $filter === 'approved' ? 'active' : ''; ?>">Onaylanan (<?php echo $approved_total_count; ?>)</a>
                <a href="designs.php?filter=revision_requested" class="filter-link <?php echo $filter === 'revision_requested' ? 'active' : ''; ?>">Revize (<?php echo (int)($count_rows['revision_requested'] ?? 0); ?>)</a>
            </div>

            <?php if (empty($drafts)): ?>
                <div class="table-container">
                    <div class="empty-state">Bu filtrede görüntülenecek tasarım bulunmuyor.</div>
                </div>
            <?php else: ?>
                <div class="design-grid">
                    <?php foreach ($drafts as $draft): ?>
                        <?php
                            $file_path = trim((string)($draft['file_path'] ?? ''));
                            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                            $asset_href = '../' . ltrim($file_path, '/');
                            $status = strtolower(trim((string)($draft['status'] ?? 'pending')));
                            $order_status = strtolower(trim((string)($draft['order_status'] ?? 'pending')));
                            $display_status = $status;
                            if ($display_status !== 'approved' && in_array($order_status, ['approved', 'completed'], true)) {
                                $display_status = 'approved';
                            }
                        ?>
                        <article class="design-card">
                            <div class="design-preview">
                                <?php if ($file_path !== '' && $is_image): ?>
                                    <img src="<?php echo htmlspecialchars($asset_href); ?>" alt="Taslak">
                                <?php else: ?>
                                    <div style="text-align:center; color:#64748b;">
                                        <i data-lucide="file-text" style="width:38px; height:38px;"></i>
                                        <p style="margin:0.4rem 0 0; font-size:0.82rem; font-weight:700;">Dosya Önizleme</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="design-meta">
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem;">
                                    <strong>Sipariş #<?php echo (int)$draft['order_id']; ?></strong>
                                    <span class="chip <?php echo status_chip_class($display_status); ?>"><?php echo htmlspecialchars(status_label($display_status)); ?></span>
                                </div>
                                <div style="font-size:0.85rem; color:#64748b;">Müşteri: <?php echo htmlspecialchars((string)$draft['customer_name']); ?></div>
                                <div style="font-size:0.82rem; color:#64748b;">Paket: <?php echo htmlspecialchars((string)($draft['package'] ?? 'classic')); ?> | Tarih: <?php echo date('d.m.Y H:i', strtotime((string)$draft['created_at'])); ?></div>
                                <div class="design-actions">
                                    <a href="order_details.php?id=<?php echo (int)$draft['order_id']; ?>" class="mini-btn primary"><i data-lucide="eye"></i> Siparişe Git</a>
                                    <?php if ($file_path !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($asset_href); ?>" target="_blank" rel="noopener" class="mini-btn"><i data-lucide="external-link"></i> Aç</a>
                                        <a href="<?php echo htmlspecialchars($asset_href); ?>" download class="mini-btn"><i data-lucide="download"></i> İndir</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
