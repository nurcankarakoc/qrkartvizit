<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();

$package_slug = strtolower(trim((string)($order['package'] ?? '')));
$is_panel_only_package = $package_slug === 'panel';
$remaining_revisions = (int)($order['revision_count'] ?? 0);
$order_status = (string)($order['status'] ?? 'pending');
$has_draft = !empty($order['draft_path']);

$success_key = trim((string)($_GET['success'] ?? ''));
$error_key = trim((string)($_GET['error'] ?? ''));
$success_messages = [
    'approved' => 'Tasarım onaylandı! Siparişiniz baskı sırasına alındı.',
    'revised' => 'Revize talebiniz başarıyla tasarım ekibine iletildi.',
    'disputed' => 'İtirazınız yöneticiye yönlendirildi.',
];
$error_messages = [
    'csrf' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.',
    'no_draft' => 'Hala tasarım bekliyoruz. Taslak yüklenmeden işlem yapılamaz.',
    'no_revision' => 'Ücretsiz revize hakkınız kalmadı.',
    'approve_failed' => 'Onay işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.',
];
$success_message = $success_messages[$success_key] ?? '';
$error_message = $error_messages[$error_key] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarım Süreci — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .design-tracker-container { max-width: 800px; margin: 0 auto; }
        .design-card { 
            background: #fff; 
            border-radius: 20px; 
            padding: 2.5rem; 
            border: 1px solid #eef2f6;
            text-align: center;
        }
        .design-frame {
            width: 100%;
            background: #f8fafc;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            transition: 0.3s;
            position: relative;
        }
        .design-frame img { 
            width: 100%; 
            height: auto; 
            border-radius: 12px; 
            display: block; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }
        .design-frame:hover { border-color: var(--navy-blue); }
        
        .action-hub { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
        .btn-premium-approve { background: #000; color: #fff; border: none; padding: 1rem 2rem; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 0.5rem; }
        .btn-premium-approve:hover { background: #333; transform: translateY(-2px); }
        
        .btn-premium-revise { background: #fff; color: #1e293b; border: 1.5px solid #e2e8f0; padding: 1rem 2rem; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-premium-revise:hover { background: #f8fafc; border-color: #cbd5e1; }
        
        .revision-form-container { margin-top: 2rem; padding: 1.5rem; background: #fcfdfe; border-radius: 16px; border: 1px solid #eef2f6; text-align: left; display: none; }
        .revision-textarea { width: 100%; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; resize: none; min-height: 100px; font-weight: 500; }
        .revision-textarea:focus { border-color: var(--navy-blue); outline: none; }

        .status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 700; font-size: 0.75rem; background: #f1f5f9; color: #475569; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.waiting { background: #fffbeb; color: #92400e; }
        .status-badge.on-air { background: #ecfdf5; color: #065f46; }

        @media (max-width: 768px) {
            .action-hub { flex-direction: column; }
            .btn-premium-approve, .btn-premium-revise { width: 100%; }
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
                <li class="active"><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li><a href="#"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?></div>
                <div class="details">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Müşteri'); ?></span>
                    <span class="role">Premium Üye</span>
                </div>
            </div>
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Tasarım Süreci</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Kartvizit tasarımınızın güncel durumunu buradan takip edin.</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.82rem; font-weight: 800; color: #94a3b8; margin-bottom: 0.4rem; text-transform: uppercase;">Kalan Ücretsiz Revize</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--gold);"><?php echo $remaining_revisions; ?></div>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($success_message): ?>
                <div style="background: #ecfdf5; color: #065f46; padding: 1.25rem; border-radius: 20px; border: 1.5px solid #10b981; margin-bottom: 2rem; font-weight: 700;">
                    <i data-lucide="check-circle" style="width: 20px; vertical-align: middle; margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 1.25rem; border-radius: 20px; border: 1.5px solid #ef4444; margin-bottom: 2rem; font-weight: 700;">
                    <i data-lucide="alert-circle" style="width: 20px; vertical-align: middle; margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="design-tracker-container">
                <div class="design-card">
                    <?php if (!$order): ?>
                        <div style="padding: 4rem 2rem;">
                            <i data-lucide="package-x" style="width:64px; height:64px; color:#cbd5e1; margin-bottom:1.5rem;"></i>
                            <h2 style="font-weight:800; color: var(--navy-blue);">Henüz bir siparişiniz bulunmuyor.</h2>
                            <p style="color: #64748b; margin-top:1rem;">Dijital kartvizitinizi oluşturmak için bir paket satın almalısınız.</p>
                        </div>
                    <?php else: ?>
                        <?php if ($is_panel_only_package): ?>
                            <div style="background: #eff6ff; color: #1e3a8a; padding: 2rem; border-radius: 24px; border: 1px solid #dbeafe; text-align: left; margin-bottom: 2rem;">
                                <h3 style="font-weight: 800; margin-bottom: 0.75rem;">Dijital Panel Aktif</h3>
                                <p style="margin: 0; font-size: 1rem; line-height: 1.6; opacity: 0.8;">Sadece Panel paketinde fiziksel tasarım onayı yoktur. Dijital kartvizitinizi profil sayfasından anında düzenlemeye başlayabilirsiniz.</p>
                            </div>
                        <?php endif; ?>

                        <div class="status-badge <?php echo $has_draft ? 'on-air' : 'waiting'; ?>">
                            <?php if($has_draft): ?><i data-lucide="sparkles"></i> Taslak Hazır!
                            <?php else: ?><i data-lucide="clock"></i> Tasarım Ekibi Hazırlıyor...<?php endif; ?>
                        </div>

                        <div class="design-frame">
                            <?php if ($has_draft): ?>
                                <!-- FIX: Path added ../ to reach assets root -->
                                <img src="../<?php echo htmlspecialchars((string)$order['draft_path']); ?>" alt="Tasarım Taslağı">
                            <?php else: ?>
                                <div style="padding: 6rem 2rem; color: #94a3b8;">
                                    <i data-lucide="palette" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p style="font-weight: 800; font-size: 1.1rem;">Tasarımınız şu an mutfakta...</p>
                                    <p style="font-size: 0.9rem; margin-top: 0.5rem; font-weight: 500;">Bitince burada muhteşem bir görsel göreceksiniz.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_draft && in_array($order_status, ['designing', 'awaiting_approval', 'revision_requested'], true)): ?>
                            <div class="action-hub">
                                <form action="../processes/order_status_update.php" method="POST">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-premium-approve">
                                        <i data-lucide="check-circle"></i> Tasarımı Onayla
                                    </button>
                                </form>

                                <button class="btn-premium-revise" onclick="document.getElementById('revision-form-area').style.display='block'; window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});">
                                    <i data-lucide="refresh-cw" style="width:18px; vertical-align:middle; margin-right:0.5rem;"></i> Revize İste
                                </button>
                            </div>

                            <div class="revision-form-container" id="revision-form-area">
                                <h4 style="font-weight: 800; color: var(--navy-blue); margin-bottom: 1.25rem;">Revize Talebiniz</h4>
                                <form action="../processes/order_status_update.php" method="POST">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input type="hidden" name="action" value="revise">
                                    <textarea name="revision_note" class="revision-textarea" placeholder="Değiştirilmesini istediğiniz kısımları detaylıca belirtin..." required></textarea>
                                    <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1.5rem;">
                                        <button type="button" class="mini-btn" onclick="document.getElementById('revision-form-area').style.display='none';" style="border:none;">Vazgeç</button>
                                        <button type="submit" class="btn-premium-approve" style="padding: 1rem 2rem;">Talebi Gönder</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php 
                if ($order) {
                    $stmt_drafts = $pdo->prepare("SELECT * FROM design_drafts WHERE order_id = ? ORDER BY created_at DESC");
                    $stmt_drafts->execute([$order['id']]);
                    $history = $stmt_drafts->fetchAll();
                    
                    if (!empty($history)): ?>
                        <div style="margin-top: 3.5rem;">
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                                <i data-lucide="history" style="color: var(--gold); width:20px;"></i> Yükleme Geçmişi
                            </h3>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                                <?php foreach ($history as $h): ?>
                                    <div style="background: #fff; border-radius: 16px; padding: 1.25rem; border: 1px solid #eef2f6; display: flex; align-items: center; gap: 1.5rem; transition: 0.2s;" onmouseover="this.style.borderColor='#e2e8f0'" onmouseout="this.style.borderColor='#eef2f6'">
                                        <div style="width: 80px; height: 80px; border-radius: 12px; overflow: hidden; background: #f8fafc; flex-shrink: 0; cursor: pointer; border: 1px solid #eef2f6;" onclick="window.open('../<?php echo htmlspecialchars($h['file_path']); ?>', '_blank')">
                                            <img src="../<?php echo htmlspecialchars($h['file_path']); ?>" alt="Draft" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-size: 0.85rem; color: #94a3b8; font-weight: 600; margin-bottom: 0.25rem;"><?php echo date('d.m.Y H:i', strtotime($h['created_at'])); ?></div>
                                            <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 800; font-size: 0.9rem; color: <?php echo $h['status'] === 'approved' ? '#059669' : '#475569'; ?>;">
                                                <i data-lucide="<?php echo $h['status'] === 'approved' ? 'check-circle' : 'clock'; ?>" style="width: 16px;"></i>
                                                <?php echo $h['status'] === 'approved' ? 'Onaylandı' : 'Beklemede'; ?>
                                            </div>
                                        </div>
                                        <button type="button" onclick="window.open('../<?php echo htmlspecialchars($h['file_path']); ?>', '_blank')" style="background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.8rem; cursor: pointer;">Görüntüle</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; 
                } ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
