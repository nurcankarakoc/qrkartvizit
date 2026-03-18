<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Sipariş bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarım Takibi — Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #0A2F2F;
            --content-bg: #f8fafc;
            --gold: #A6803F;
            --navy-blue: #0A2F2F;
            --navy-dark: #072424;
        }

        body { background: var(--content-bg); display: flex; min-height: 100vh; }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 3rem; }

        .card { background: #fff; border-radius: 20px; padding: 2.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .design-preview {
            width: 100%;
            max-width: 600px;
            margin: 0 auto 3rem;
            background: #f1f5f9;
            border-radius: 20px;
            overflow: hidden;
            aspect-ratio: 1.7 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .design-preview img { width: 100%; height: 100%; object-fit: contain; }

        .empty-preview { text-align: center; color: #94a3b8; padding: 2rem; }

        .action-flex { display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; }

        .btn-approve { background: #10b981; color: #fff; border:none; padding: 1.2rem 3rem; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 1rem; }
        .btn-approve:hover { background: #059669; transform: scale(1.02); }

        .btn-revise { background: #fff; color: #1e293b; border: 2px solid #e2e8f0; padding: 1.2rem 3rem; border-radius: 14px; font-weight: 800; cursor: pointer; transition: 0.3s; }
        .btn-revise:hover { border-color: #cbd5e1; background: #f8fafc; }

        .revision-badge { background: #fff7ed; color: var(--gold); padding: 0.5rem 1rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; border: 1px solid #ffedd5; }

        .menu-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; color: rgba(255,255,255,0.6); text-decoration: none; border-radius: 12px; margin-bottom: 0.5rem; transition: all 0.3s; font-weight: 500; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); color: #fff; }
        .menu-item i { width: 20px; height: 20px; }

        .buy-extra { margin-top: 4rem; background: linear-gradient(135deg, var(--navy-dark), var(--navy-blue)); border-radius: 20px; padding: 2.5rem; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        
        .form-control { width: 100%; padding: 0.8rem 1.2rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; background: #f8fafc; transition: 0.3s; margin-top: 1rem; }
        .btn-save { background: var(--navy-blue); color: #fff; border: none; padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }

        @media (max-width: 1024px) {
            body { display: block; }
            .main-content { margin-left: 0; padding: 1rem; }
        }

        @media (max-width: 768px) {
            .card { padding: 1rem; border-radius: 16px; }
            header[style*="display: flex"] { flex-direction: column; align-items: flex-start !important; gap: 0.75rem; }
            .action-flex { flex-direction: column; }
            .btn-approve, .btn-revise, .btn-save { width: 100%; min-height: 44px; justify-content: center; }
            .buy-extra { flex-direction: column; align-items: flex-start; gap: 1rem; padding: 1.25rem; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i data-lucide="layout-dashboard"></i><span>Genel Bakış</span></a>
            <a href="profile.php" class="menu-item"><i data-lucide="user-cog"></i><span>Profilimi Düzenle</span></a>
            <a href="design-tracking.php" class="menu-item active"><i data-lucide="palette"></i><span>Tasarım Süreci</span></a>
            <a href="#" class="menu-item"><i data-lucide="shopping-bag"></i><span>Siparişlerim</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="margin-bottom: 3rem; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--navy-blue);">Tasarım Takibi</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Tasarımcımız tarafından hazırlanan kartvizit taslağınızı buradan onaylayabilirsiniz.</p>
            </div>
            <div class="revision-badge">
                <i data-lucide="refresh-cw" style="width: 14px; vertical-align: middle; margin-right: 0.5rem;"></i>
                Kalan Ücretsiz Revize: <?php echo $order['revision_count'] ?? 0; ?>
            </div>
        </header>

        <div class="card" style="text-align: center;">
            <div class="design-preview">
                <?php if($order && isset($order['draft_path']) && $order['draft_path']): ?>
                    <img src="<?php echo $order['draft_path']; ?>" alt="Design Draft">
                <?php else: ?>
                    <div class="empty-preview">
                        <i data-lucide="image" style="width: 64px; height: 64px; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p style="font-weight: 600; color: var(--navy-blue);">Tasarımınız henüz hazırlanmamıştır.</p>
                        <p style="font-size: 0.85rem; margin-top: 0.5rem;">Tasarımcımız hazırladığında burada görünecektir.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if($order && isset($order['status']) && ($order['status'] == 'designing' || $order['status'] == 'awaiting_approval' || $order['status'] == 'revision_requested') && isset($order['draft_path']) && $order['draft_path']): ?>
                <div class="action-flex">
                    <form action="../processes/order_status_update.php" method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn-approve">
                            <i data-lucide="check-circle"></i> Tasarımı Onayla
                        </button>
                    </form>

                    <button class="btn-revise" onclick="document.getElementById('revision-form').style.display='block'; document.getElementById('dispute-form').style.display='none';">
                        Revize İste
                    </button>

                    <button class="btn-revise" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.2);" onclick="document.getElementById('dispute-form').style.display='block'; document.getElementById('revision-form').style.display='none';">
                        <i data-lucide="alert-triangle" style="width: 14px; vertical-align: middle; margin-right: 0.2rem;"></i> Hata Bildir
                    </button>
                </div>
                
                <div id="dispute-form" style="display:none; margin-top: 2rem; text-align: left; padding: 2rem; background: #fef2f2; border-radius: 12px; border: 1px solid #fee2e2;">
                    <form action="../processes/order_status_update.php" method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="dispute">
                        <label style="font-weight: 700; margin-bottom: 1rem; display: block; color: #b91c1c;">Hata Bildirimi (İtiraz)</label>
                        <p style="font-size: 0.8rem; color: #991b1b; margin-bottom: 1rem;">Tasarımcı hatasından (yanlış bilgi, eksik logo vb.) dolayı itiraz ediyorsanız lütfen belirtin. Haklı bulunursanız revize hakkınız düşmeyecektir.</p>
                        <textarea name="dispute_reason" class="form-control" rows="3" placeholder="Örn: Telefon numaram yanlış yazılmış, taslakta belirttiğim logom eksik..." required></textarea>
                        <button type="submit" class="btn-save" style="margin-top: 1rem; background: #ef4444;">İtirazı Gönder</button>
                    </form>
                </div>
                
                <div id="revision-form" style="display:none; margin-top: 2rem; text-align: left; padding: 2rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <form action="../processes/order_status_update.php" method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <input type="hidden" name="action" value="revise">
                        <label style="font-weight: 700; margin-bottom: 1rem; display: block; color: var(--navy-blue);">Revize Notlarınız</label>
                        <textarea name="revision_notes" class="form-control" rows="3" placeholder="Logoyu biraz daha sağa kaydırabilir miyiz? Renkler daha canlı olsun..." required></textarea>
                        <button type="submit" class="btn-save" style="margin-top: 1rem;">Notları Gönder</button>
                    </form>
                </div>
            <?php elseif($order && isset($order['status']) && $order['status'] == 'approved'): ?>
                <div style="background: #dcfce7; color: #166534; padding: 2.5rem; border-radius: 16px; border: 1px solid #bbfcce;">
                    <i data-lucide="check-circle" style="width: 48px; height: 48px; margin-bottom: 1rem;"></i>
                    <h4 style="font-weight: 800; font-size: 1.25rem;">Tasarım Onaylandı!</h4>
                    <p>Kartvizitleriniz baskı sırasına alınmıştır. Süreç tamamlandığında bilgilendirileceksiniz.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if(($order['revision_count'] ?? 0) <= 0): ?>
            <div class="buy-extra">
                <div>
                    <h3 style="font-weight: 800; font-size: 1.5rem; margin-bottom: 0.5rem;">Ücretsiz Revize Hakkınız Bitti</h3>
                    <p style="opacity: 0.7;">Endişelenmeyin! Uygun fiyata ek revize paketi satın alarak sürece devam edebilirsiniz.</p>
                </div>
                <a href="#" class="btn-approve" style="background: var(--gold); color: #fff;">
                    <i data-lucide="zap"></i> Ek Revize Al (99 ₺)
                </a>
            </div>
        <?php endif; ?>

    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script src="../assets/js/mobile-form.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
