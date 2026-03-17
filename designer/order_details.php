<?php
session_start();
require_once '../core/db.php';

// Designer check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    header("Location: ../auth/login.php");
    exit();
}

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: dashboard.php");
    exit();   
}

// Fetch order details
$stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id
                       WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Smart Package check
$is_smart = (stripos($order['package'], 'Akıllı') !== false || stripos($order['package'], 'smart') !== false);

// Fetch profile if exists (for QR code)
$stmt_profile = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_profile->execute([$order['user_id']]);
$profile = $stmt_profile->fetch();

// Fetch existing drafts
try {
    $stmt_drafts = $pdo->prepare("SELECT * FROM design_drafts WHERE order_id = ? ORDER BY created_at DESC");
    $stmt_drafts->execute([$order_id]);
    $drafts = $stmt_drafts->fetchAll();
} catch (Exception $e) {
    $drafts = [];
}

// Update status to 'designing' if it's 'pending'
if ($order['status'] == 'pending') {
    $pdo->prepare("UPDATE orders SET status = 'designing' WHERE id = ?")->execute([$order_id]);
    $order['status'] = 'designing';
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı #<?php echo $order_id; ?> — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .order-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; }
        .card-details { background: #fff; padding: 2.5rem; border-radius: 24px; box-shadow: var(--card-shadow); }
        .card-sidebar-info { display: flex; flex-direction: column; gap: 2rem; }
        .info-box { background: #fff; padding: 2rem; border-radius: 24px; box-shadow: var(--card-shadow); }
        .info-box h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .info-box h3 i { color: var(--primary); width: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.95rem; }
        .detail-row span:first-child { color: #64748b; font-weight: 500; }
        .detail-row span:last-child { color: #1e293b; font-weight: 700; }
        .design-notes { background: #f8fafc; padding: 1.5rem; border-radius: 16px; border-left: 4px solid var(--primary); margin-top: 2rem; line-height: 1.6; color: #475569; }
        .logo-preview { margin-top: 2rem; }
        .logo-preview img { max-width: 200px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1rem; background: #f8fafc; }
        .upload-zone { margin-top: 3rem; border: 2px dashed #cbd5e1; padding: 3rem; border-radius: 24px; text-align: center; transition: all 0.3s; cursor: pointer; position: relative; }
        .upload-zone:hover { border-color: var(--primary); background: #f0f9ff; }
        .upload-zone input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-zone i { font-size: 3rem; color: #94a3b8; display: block; margin: 0 auto 1.5rem; }
        .upload-zone p { font-weight: 700; color: #475569; }
        .btn-submit-draft { width: 100%; padding: 1.2rem; background: var(--primary); color: #fff; border: none; border-radius: 14px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 1.5rem; transition: all 0.3s; }
        .btn-submit-draft:hover { background: #2563eb; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2); }
        .qr-area { text-align: center; padding: 1.5rem; background: #f8fafc; border-radius: 16px; margin-top: 1rem; }
        .qr-area img { width: 150px; height: 150px; display: block; margin: 0 auto 1rem; }
        .qr-area a { font-size: 0.85rem; font-weight: 700; color: var(--primary); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .draft-history { margin-top: 3rem; }
        .draft-card { display: flex; align-items: center; gap: 1.5rem; padding: 1.2rem; background: #f8fafc; border-radius: 16px; margin-bottom: 1rem; }
        .draft-thumb { width: 80px; height: 80px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
        .draft-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .draft-info { flex-grow: 1; }
        .draft-status.approved { color: #16a34a; font-weight: 700; }
        .draft-status.pending { color: #3b82f6; font-weight: 700; }
        .status-chip { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #eff6ff; color: #1d4ed8; }
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
                    <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Panel</a></li>
                    <li><a href="#"><i data-lucide="image"></i> Tasarımlarım</a></li>
                    <li><a href="#"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
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
                <a href="../processes/logout.php" class="logout-btn"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <a href="dashboard.php" class="btn-action">
                    <i data-lucide="arrow-left" style="width: 16px; margin-right: 0.5rem; vertical-align: middle;"></i> Geri Dön
                </a>
                <div class="order-id-badge">Sipariş ID: #<?php echo $order_id; ?></div>
            </div>

            <div class="order-grid">
                <div class="main-pane">
                    <div class="card-details">
                        <h1>Tasarım Bilgileri</h1>
                        <div class="design-notes"><strong>Müşteri Notu:</strong><br><?php echo nl2br(htmlspecialchars($order['design_notes'] ?: 'Not eklenmemiş.')); ?></div>

                        <?php if ($order['logo_path']): ?>
                            <div class="logo-preview">
                                <strong>Logo:</strong><br><br>
                                <img src="../<?php echo $order['logo_path']; ?>" alt="Logo">
                                <br><br>
                                <a href="../<?php echo $order['logo_path']; ?>" download class="btn-action"><i data-lucide="download"></i> Logoyu İndir</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_smart): ?>
                            <div style="margin-top: 3rem;">
                                <strong>Dinamik QR Kod (Akıllı Paket):</strong>
                                <div class="qr-area">
                                    <?php if ($profile && isset($profile['qr_path'])): ?>
                                        <img src="../<?php echo $profile['qr_path']; ?>" alt="QR Code">
                                        <a href="../<?php echo $profile['qr_path']; ?>" download><i data-lucide="download"></i> QR İndir</a>
                                    <?php else: ?>
                                        <div style="padding: 2rem; color: #94a3b8;"><i data-lucide="alert-triangle"></i><br>QR Kod henüz üretilmemiş.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="upload-section">
                            <h2 style="margin-top: 3rem; margin-bottom: 1.5rem;">Yeni Taslak Yükle</h2>
                            <form action="../processes/designer_upload.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <div class="upload-zone" id="uploadZone">
                                    <i data-lucide="upload-cloud"></i>
                                    <p>Taslak Dosyasını Buraya Sürükleyin veya Tıklayın</p>
                                    <input type="file" name="draft_file" id="draftFile" required>
                                </div>
                                <div id="fileNameDisplay" style="margin-top: 1rem; font-weight: 700; color: var(--primary); display: none;"></div>
                                <button type="submit" class="btn-submit-draft">Taslağı Müşteriye Gönder</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="sidebar-pane">
                    <div class="card-sidebar-info">
                        <div class="info-box">
                            <h3><i data-lucide="user"></i> Müşteri Bilgileri</h3>
                            <div class="detail-row"><span>Ad Soyad:</span><span><?php echo htmlspecialchars($order['customer_name']); ?></span></div>
                            <div class="detail-row"><span>Şirket:</span><span><?php echo htmlspecialchars($order['company_name']); ?></span></div>
                        </div>
                        <div class="info-box">
                            <h3><i data-lucide="package"></i> Sipariş Özeti</h3>
                            <div class="detail-row"><span>Paket:</span><span><?php echo htmlspecialchars($order['package']); ?></span></div>
                            <div class="detail-row"><span>Durum:</span><span class="status-chip"><?php echo htmlspecialchars($order['status']); ?></span></div>
                            <div class="detail-row"><span>Kalan Revize:</span><span><?php echo $order['revision_count']; ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
        document.getElementById('draftFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0].name;
            const display = document.getElementById('fileNameDisplay');
            display.textContent = 'Seçilen Dosya: ' + fileName;
            display.style.display = 'block';
        });
    </script>
</body>
</html>
