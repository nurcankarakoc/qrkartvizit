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
    'extra_revision_purchased' => 'Ek revize paketi hesabınıza tanımlandı.',
];
$error_messages = [
    'csrf' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.',
    'no_draft' => 'Henüz tasarım yüklenmediği için bu işlem yapılamaz.',
    'no_revision' => 'Ücretsiz revize hakkınız kalmadı.',
    'approve_failed' => 'Onay işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.',
    'invalid_request' => 'Geçersiz işlem talebi alındı.',
    'unauthorized' => 'Bu sipariş üzerinde işlem yetkiniz yok.',
    'invalid_transition' => 'Bu adım mevcut sipariş durumunda kullanılamıyor.',
    'revision_note_required' => 'Revize notu zorunludur.',
    'dispute_reason_required' => 'İtiraz nedeni zorunludur.',
    'dispute_failed' => 'İtiraz kaydı oluşturulamadı. Lütfen tekrar deneyin.',
    'extra_revision_failed' => 'Ek revize satın alma işlemi tamamlanamadı.',
];
$success_message = $success_messages[$success_key] ?? '';
$error_message = $error_messages[$error_key] ?? '';

function table_exists(PDO $pdo, string $table): bool
{
    $table_escaped = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
    return (bool)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarım Süreci - Zerosoft</title>
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
            border-radius: 24px; 
            padding: 2.5rem; 
            border: 1px solid #eef2f6;
            text-align: center;
            position: relative;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 30px rgba(10, 47, 47, 0.02);
        }
        .design-card::before { content: ''; position: absolute; inset: 0; border-radius: 24px; padding: 1px; background: linear-gradient(135deg, var(--gold), transparent 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.5s ease; z-index: -1; pointer-events: none; }
        .design-card:hover { border-color: transparent; transform: translateY(-3px); box-shadow: 0 15px 50px rgba(166, 128, 63, 0.08); }
        .design-card:hover::before { opacity: 1; }

        .design-frame {
            width: 100%;
            background: #f8fafc;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 1;
        }
        .design-frame::before { content: ''; position: absolute; inset: 0; border-radius: 16px; padding: 2px; background: linear-gradient(135deg, var(--gold), transparent 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.5s ease; z-index: -1; pointer-events: none; }
        .design-frame:hover { border-color: transparent; box-shadow: 0 15px 40px rgba(166,128,63,0.08); transform: scale(1.005); }
        .design-frame:hover::before { opacity: 1; }

        .design-frame img { 
            width: 100%; 
            height: auto; 
            border-radius: 12px; 
            display: block; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }
        
        .history-card { background: #fff; border-radius: 16px; padding: 1.25rem; border: 1px solid #eef2f6; display: flex; align-items: center; gap: 1.5rem; position: relative; z-index: 1; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .history-card::before { content: ''; position: absolute; inset: 0; border-radius: 16px; padding: 1px; background: linear-gradient(135deg, rgba(166,128,63,0.8), transparent 80%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask-composite: exclude; opacity: 0; transition: opacity 0.4s ease; z-index: -1; pointer-events: none; }
        .history-card:hover { border-color: transparent; box-shadow: 0 8px 25px rgba(166,128,63,0.06); transform: translateX(8px); }
        .history-card:hover::before { opacity: 1; }
        
        .action-hub { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; }
        .btn-premium-approve {
            background:
                linear-gradient(135deg, #083030, #0f4a4a) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #fff;
            border: 1px solid transparent;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 12px 24px rgba(6, 36, 36, 0.24);
        }
        .btn-premium-approve:hover {
            background:
                linear-gradient(135deg, #0a3c3c, #125757) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(166, 128, 63, 0.24);
        }
        
        .btn-premium-revise {
            background:
                linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            color: #1e293b;
            border: 1.5px solid transparent;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s ease;
        }
        .btn-premium-revise:hover {
            background:
                linear-gradient(#f8fafc, #f8fafc) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 0.98) 0%, rgba(214, 180, 106, 0.82) 52%, rgba(166, 128, 63, 0.95) 100%) border-box;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(166, 128, 63, 0.16);
        }
        
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
                <li><a href="orders.php"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
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

                                <?php if ($remaining_revisions > 0): ?>
                                    <button type="button" class="btn-premium-revise" onclick="document.getElementById('revision-form-area').style.display='block'; window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'});">
                                        <i data-lucide="refresh-cw" style="width:18px; vertical-align:middle; margin-right:0.5rem;"></i> Revize İste
                                    </button>
                                <?php else: ?>
                                    <div style="display:flex; align-items:center; justify-content:center; padding:0.8rem 1rem; border:1px solid #fecaca; background:#fef2f2; color:#991b1b; border-radius:12px; font-weight:700;">
                                        Ücretsiz revize hakkınız bitti.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($remaining_revisions > 0): ?>
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
                            <?php else: ?>
                                <div class="revision-form-container" style="display:block; text-align:left;">
                                    <h4 style="font-weight: 800; color: var(--navy-blue); margin-bottom: 0.9rem;">Ek Revize Satın Al</h4>
                                    <p style="margin:0 0 1rem; color:#64748b; font-size:0.92rem;">Ek revize birim fiyatı 99 TL'dir. İhtiyacınıza göre adet seçebilirsiniz.</p>
                                    <form action="../processes/order_status_update.php" method="POST" style="display:flex; gap:0.75rem; align-items:flex-end; flex-wrap:wrap;">
                                        <?php echo csrf_input(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <input type="hidden" name="action" value="buy_extra_revision">
                                        <div>
                                            <label for="extra-revision-qty" style="display:block; font-size:0.82rem; color:#64748b; font-weight:700; margin-bottom:0.4rem;">Adet</label>
                                            <input id="extra-revision-qty" type="number" name="extra_revision_qty" min="1" max="20" value="1" class="revision-textarea" style="min-height:44px; max-width:110px; padding:0.7rem 0.9rem;">
                                        </div>
                                        <button type="submit" class="btn-premium-approve" style="padding:0.9rem 1.2rem;">
                                            <i data-lucide="shopping-cart"></i> Revize Paketi Satın Al
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php
                if ($order && table_exists($pdo, 'design_drafts')) {
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
                                    <div class="history-card">
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
