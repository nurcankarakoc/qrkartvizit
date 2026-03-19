<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$stmt_disputes = $pdo->query("SELECT d.*, u.name as customer_name, o.id as order_id, o.package
                              FROM disputes d
                              JOIN users u ON d.user_id = u.id
                              JOIN orders o ON d.order_id = o.id
                              ORDER BY CASE WHEN d.status = 'pending' THEN 0 ELSE 1 END, d.created_at DESC");
$disputes = $stmt_disputes->fetchAll();

$message_key = trim((string)($_GET['msg'] ?? ''));
$message_map = [
    'resolved' => ['type' => 'success', 'text' => 'Uyusmazlik karara baglandi.'],
    'invalid' => ['type' => 'error', 'text' => 'Gecersiz istek alindi.'],
    'error' => ['type' => 'error', 'text' => 'Islem sirasinda bir hata olustu.'],
];
$flash = $message_map[$message_key] ?? null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uyusmazlik Cozumu - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .dispute-card { background: #fff; padding: 2rem; border-radius: 20px; box-shadow: var(--card-shadow); margin-bottom: 2rem; border-left: 5px solid #ef4444; }
        .dispute-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; }
        .status-badge.pending { background: #fef2f2; color: #ef4444; }
        .status-badge.resolved_favor_customer { background: #dcfce7; color: #166534; }
        .status-badge.resolved_favor_designer { background: #e2e8f0; color: #334155; }
        .resolution-area { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; display: grid; gap: 0.75rem; }
        .btn-resolve { padding: 0.8rem 1.5rem; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 0.85rem; }
        .btn-resolve.customer { background: #16a34a; color: #fff; }
        .btn-resolve.designer { background: #334155; color: #fff; }
        .admin-note { width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.7rem; font-size: 0.85rem; resize: vertical; min-height: 70px; }
        .flash { margin-bottom: 1rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.9rem; font-weight: 700; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
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
                    <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakis</a></li>
                    <li><a href="orders.php"><i data-lucide="shopping-cart"></i> Tum Siparisler</a></li>
                    <li><a href="designers.php"><i data-lucide="users"></i> Tasarimci Yonetimi</a></li>
                    <li class="active"><a href="disputes.php"><i data-lucide="alert-circle"></i> Uyusmazliklar</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar">A</div>
                    <div class="details"><span class="name">Super Admin</span><span class="role">Zerosoft</span></div>
                </div>
                <a href="../processes/logout.php" class="logout-btn"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <h1>Uyusmazlik Cozum Merkezi</h1>
            </header>

            <?php if ($flash): ?>
                <div class="flash <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <div class="content-wrapper">
                <p style="color: #64748b; margin-bottom: 2rem;">Musteri itirazlarini inceleyip sonuclandirin.</p>

                <?php if (empty($disputes)): ?>
                    <div class="empty-state">Kayitli uyusmazlik bulunmuyor.</div>
                <?php else: ?>
                    <?php foreach ($disputes as $dispute): ?>
                        <?php $status = (string)($dispute['status'] ?? 'pending'); ?>
                        <div class="dispute-card" style="<?php echo $status !== 'pending' ? 'border-left-color:#cbd5e1; opacity:0.8;' : ''; ?>">
                            <div class="dispute-header">
                                <div>
                                    <h3 style="font-weight: 800;"><?php echo htmlspecialchars((string)$dispute['customer_name']); ?> - Siparis #<?php echo (int)$dispute['order_id']; ?></h3>
                                    <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.2rem;">
                                        Paket: <?php echo htmlspecialchars((string)$dispute['package']); ?> |
                                        Tarih: <?php echo date('d.m.Y H:i', strtotime((string)$dispute['created_at'])); ?>
                                    </p>
                                </div>
                                <span class="badge status-badge <?php echo htmlspecialchars($status); ?>">
                                    <?php echo htmlspecialchars($status === 'pending' ? 'Bekleyen Itiraz' : 'Cozumlendi'); ?>
                                </span>
                            </div>

                            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; font-size: 0.95rem; color: #475569;">
                                <strong>Musteri Itiraz Notu:</strong><br>
                                <?php echo nl2br(htmlspecialchars((string)$dispute['reason'])); ?>
                            </div>

                            <?php if (!empty($dispute['admin_note'])): ?>
                                <div style="margin-top:0.8rem; background:#f1f5f9; padding:0.9rem; border-radius:10px; font-size:0.86rem; color:#334155;">
                                    <strong>Admin Notu:</strong> <?php echo nl2br(htmlspecialchars((string)$dispute['admin_note'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($status === 'pending'): ?>
                                <div class="resolution-area">
                                    <textarea id="admin-note-<?php echo (int)$dispute['id']; ?>" class="admin-note" placeholder="Cozum notu (opsiyonel)"></textarea>

                                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                                        <form id="resolve-customer-<?php echo (int)$dispute['id']; ?>" action="../processes/admin_actions.php" method="POST">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="resolve_dispute">
                                            <input type="hidden" name="dispute_id" value="<?php echo (int)$dispute['id']; ?>">
                                            <input type="hidden" name="resolution" value="favor_customer">
                                            <input type="hidden" name="admin_note" class="admin-note-input">
                                            <button type="submit" class="btn-resolve customer"><i data-lucide="check-circle" style="width: 14px; vertical-align: middle; margin-right: 0.5rem;"></i> Musteriyi Hakli Bul</button>
                                        </form>

                                        <form action="../processes/admin_actions.php" method="POST">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="resolve_dispute">
                                            <input type="hidden" name="dispute_id" value="<?php echo (int)$dispute['id']; ?>">
                                            <input type="hidden" name="resolution" value="favor_designer">
                                            <input type="hidden" name="admin_note" class="admin-note-input">
                                            <button type="submit" class="btn-resolve designer">Tasarimciyi Hakli Bul</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
        document.querySelectorAll('.resolution-area').forEach((area) => {
            const note = area.querySelector('.admin-note');
            if (!note) return;
            area.querySelectorAll('form').forEach((form) => {
                form.addEventListener('submit', () => {
                    const hidden = form.querySelector('.admin-note-input');
                    if (hidden) {
                        hidden.value = note.value;
                    }
                });
            });
        });
    </script>
</body>
</html>
