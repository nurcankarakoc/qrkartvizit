<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

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

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

function has_digital_profile_package(?string $package): bool
{
    if (!is_string($package) || $package === '') {
        return false;
    }

    $normalized = strtolower(trim($package));
    return str_contains($normalized, 'panel')
        || str_contains($normalized, 'smart')
        || str_contains($normalized, 'akilli');
}

function project_base_url_for_designer(): string
{
    $env_app_url = trim((string)getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/designer/order_details.php');
    $project_path = preg_replace('#/designer/[^/]+$#', '', $script_name);
    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

function build_dynamic_qr_url(string $target_url): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&ecc=M&data=' . rawurlencode($target_url);
}

// Digital package check
$package_value = (string)($order['package'] ?? '');
$has_digital_profile = has_digital_profile_package($package_value);

// Fetch profile if exists (for QR code)
$profile_order_col = table_has_column($pdo, 'profiles', 'created_at') ? 'created_at' : 'id';
$stmt_profile = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY {$profile_order_col} DESC LIMIT 1");
$stmt_profile->execute([$order['user_id']]);
$profile = $stmt_profile->fetch();
$public_profile_url = '';

if ($profile && !empty($profile['slug'])) {
    $public_profile_url = project_base_url_for_designer() . '/kartvizit.php?slug=' . rawurlencode((string)$profile['slug']);
}
$resolved_qr_path = trim((string)($profile['qr_path'] ?? ''));
if ($resolved_qr_path === '' && $public_profile_url !== '') {
    $resolved_qr_path = build_dynamic_qr_url($public_profile_url);
}
$resolved_qr_asset = $resolved_qr_path;
if (
    $resolved_qr_asset !== '' &&
    !preg_match('#^https?://#i', $resolved_qr_asset) &&
    !str_starts_with($resolved_qr_asset, '../') &&
    !str_starts_with($resolved_qr_asset, '/')
) {
    $resolved_qr_asset = '../' . $resolved_qr_asset;
}

// Fetch existing drafts
try {
    $stmt_drafts = $pdo->prepare("SELECT * FROM design_drafts WHERE order_id = ? ORDER BY created_at DESC");
    $stmt_drafts->execute([$order_id]);
    $drafts = $stmt_drafts->fetchAll();
} catch (Exception $e) {
    $drafts = [];
}

// Move order into designing state when designer opens new work.
if (in_array((string)$order['status'], ['pending', 'pending_design'], true)) {
    $pdo->prepare("UPDATE orders SET status = 'designing' WHERE id = ?")->execute([$order_id]);
    $order['status'] = 'designing';
}

$upload_success = (($_GET['success'] ?? '') === 'uploaded');
$upload_error_key = (string)($_GET['error'] ?? '');
$upload_error_messages = [
    'csrf' => 'Guvenlik dogrulamasi basarisiz oldu. Lutfen sayfayi yenileyip tekrar deneyin.',
    'file_type' => 'Sadece JPG, PNG, WEBP veya PDF dosyasi yukleyebilirsiniz.',
    'file_mime' => 'Dosya turu dogrulanamadi. Farkli bir formatta tekrar kaydedip deneyin.',
    'file_size' => 'Dosya boyutu 10MB sinirini asiyor.',
    'upload_ini_size' => 'Sunucu dosya limiti asildi. Dosyayi kucultup tekrar deneyin.',
    'upload_form_size' => 'Dosya form limitini asti.',
    'upload_partial' => 'Dosya eksik yuklendi. Internet baglantinizi kontrol edin.',
    'upload_no_file' => 'Lutfen once bir dosya secin.',
    'upload_tmp_dir' => 'Sunucuda gecici klasor bulunamadi.',
    'upload_cant_write' => 'Dosya diske yazilamadi.',
    'upload_extension' => 'Yukleme bir PHP eklentisi tarafindan durduruldu.',
    'upload_unknown' => 'Dosya yuklenirken bilinmeyen bir hata olustu.',
    'upload_dir' => 'Yukleme klasoru olusturulamadi.',
    'move' => 'Secilen dosya hedef klasore tasinamadi.',
    'db' => 'Dosya yuklendi ancak veritabani kaydi yapilamadi.',
];
$upload_error_message = $upload_error_messages[$upload_error_key] ?? '';

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
        .card-details { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2.5rem; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px rgba(10, 47, 47, 0.03); 
        }
        .card-sidebar-info { display: flex; flex-direction: column; gap: 2rem; }
        .info-box { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2rem; 
            border-radius: 24px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px rgba(10, 47, 47, 0.03); 
        }
        .info-box h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .info-box h3 i { color: var(--gold); width: 20px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.95rem; }
        .detail-row span:first-child { color: #64748b; font-weight: 500; }
        .detail-row span:last-child { color: #1e293b; font-weight: 700; }
        .design-notes { background: #f8fafc; padding: 1.5rem; border-radius: 16px; border-left: 4px solid var(--gold); margin-top: 2rem; line-height: 1.6; color: #475569; }
        .logo-preview { margin-top: 2rem; }
        .logo-preview img { max-width: min(200px, 100%); height: auto; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1rem; background: #f8fafc; }
        .upload-zone { margin-top: 3rem; border: 2px dashed #cbd5e1; padding: 3rem; border-radius: 24px; text-align: center; transition: all 0.3s; cursor: pointer; position: relative; }
        .upload-zone:hover { border-color: var(--gold); background: #fffbeb; }
        .upload-zone input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-zone i { font-size: 3rem; color: #94a3b8; display: block; margin: 0 auto 1.5rem; }
        .upload-zone p { font-weight: 700; color: #475569; }
        .btn-submit-draft { width: 100%; padding: 1.2rem; background: var(--navy-blue); color: #fff; border: none; border-radius: 14px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 1.5rem; transition: all 0.3s; }
        .btn-submit-draft:hover { background: var(--navy-dark); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .qr-area { text-align: center; padding: 1.5rem; background: #f8fafc; border-radius: 16px; margin-top: 1rem; }
        .qr-area img { width: min(150px, 100%); height: auto; display: block; margin: 0 auto 1rem; }
        .qr-area a { font-size: 0.85rem; font-weight: 700; color: var(--gold); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .draft-history { margin-top: 3rem; }
        .draft-card { display: flex; align-items: center; gap: 1.5rem; padding: 1.2rem; background: #f8fafc; border-radius: 16px; margin-bottom: 1rem; }
        .draft-thumb { width: 80px; height: 80px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
        .draft-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .draft-info { flex-grow: 1; }
        .draft-status.approved { color: #16a34a; font-weight: 700; }
        .draft-status.pending { color: var(--navy-blue); font-weight: 700; }
        .status-chip { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #eff6ff; color: var(--navy-blue); }
        .upload-alert { margin-top: 1rem; margin-bottom: 1.5rem; padding: 0.9rem 1rem; border-radius: 12px; font-weight: 600; }
        .upload-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .upload-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 768px) {
            .card-details,
            .info-box { padding: 1rem; border-radius: 16px; }
            .upload-zone { padding: 1.25rem; }
            .btn-submit-draft, .btn-action { min-height: 44px; width: 100%; justify-content: center; }
            .detail-row { gap: 0.75rem; align-items: flex-start; }
            .detail-row span:last-child { text-align: right; }
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
                    <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Panel</a></li>
                    <li><a href="designs.php"><i data-lucide="image"></i> Tasarımlarım</a></li>
                    <li><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
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
                <div class="order-id-badge" style="font-weight: 800; color: var(--navy-blue);">Sipariş ID: #<?php echo $order_id; ?></div>
            </div>

            <?php if ($upload_success): ?>
                <div class="upload-alert success">Taslak basariyla gonderildi. Musteri panelinde onaya dustu.</div>
            <?php elseif ($upload_error_message !== ''): ?>
                <div class="upload-alert error"><?php echo htmlspecialchars($upload_error_message); ?></div>
            <?php endif; ?>

            <div class="order-grid">
                <div class="main-pane">
                    <div class="card-details">
                        <h1 style="color: var(--navy-blue); margin-bottom: 2rem;">Tasarım Bilgileri</h1>
                        <div class="design-notes"><strong>Müşteri Notu:</strong><br><?php echo nl2br(htmlspecialchars($order['design_notes'] ?: 'Not eklenmemiş.')); ?></div>

                        <?php if ($order['logo_path']): ?>
                            <div class="logo-preview">
                                <strong>Logo:</strong><br><br>
                                <img src="../<?php echo $order['logo_path']; ?>" alt="Logo">
                                <br><br>
                                <a href="../<?php echo $order['logo_path']; ?>" download class="btn-action"><i data-lucide="download"></i> Logoyu İndir</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_digital_profile): ?>
                            <div style="margin-top: 3rem;">
                                <strong>Dijital Profil ve QR:</strong>
                                <div class="qr-area">
                                    <?php if ($resolved_qr_asset !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($resolved_qr_asset); ?>" alt="QR Code">
                                        <a href="../processes/designer_qr_download.php?order_id=<?php echo (int)$order_id; ?>"><i data-lucide="download"></i> QR İndir</a>
                                    <?php elseif ($public_profile_url !== ''): ?>
                                        <div style="padding: 1.5rem 1rem; color: #334155;">
                                            <i data-lucide="link-2" style="margin-bottom: 0.5rem;"></i>
                                            <p style="margin: 0 0 0.5rem; font-weight: 700;">QR gorseli henuz yuklenmedi.</p>
                                            <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" rel="noopener" style="word-break: break-all; font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($public_profile_url); ?>
                                            </a>
                                            <a href="../processes/designer_qr_download.php?order_id=<?php echo (int)$order_id; ?>" style="margin-top:0.7rem; display:inline-flex; align-items:center; gap:0.35rem; font-size:0.84rem; font-weight:700;">
                                                <i data-lucide="download"></i> QR İndir
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div style="padding: 2rem; color: #94a3b8;"><i data-lucide="alert-triangle"></i><br>Profil bilgisi henuz olusturulmamis.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="upload-section">
                            <h2 style="margin-top: 3rem; margin-bottom: 1.5rem; color: var(--navy-dark);">Yeni Taslak Yükle</h2>
                            <form action="../processes/designer_upload.php" method="POST" enctype="multipart/form-data">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <div class="upload-zone" id="uploadZone">
                                    <i data-lucide="upload-cloud"></i>
                                    <p>Taslak Dosyasını Buraya Sürükleyin veya Tıklayın</p>
                                    <input type="file" name="draft_file" id="draftFile" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                                </div>
                                <div id="fileNameDisplay" style="margin-top: 1rem; font-weight: 700; color: var(--gold); display: none;"></div>
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
                            <div class="detail-row"><span>Email:</span><span><?php echo htmlspecialchars($order['customer_email']); ?></span></div>
                        </div>
                        <div class="info-box">
                            <h3><i data-lucide="package"></i> Sipariş Özeti</h3>
                            <div class="detail-row"><span>Paket:</span><span><?php echo htmlspecialchars($order['package'] ?? 'classic'); ?></span></div>
                            <div class="detail-row"><span>Durum:</span><span class="status-chip"><?php echo htmlspecialchars($order['status']); ?></span></div>
                            <div class="detail-row"><span>Kalan Revize:</span><span><?php echo (int)($order['revision_count'] ?? 0); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    
    <script>
        lucide.createIcons();
        document.getElementById('draftFile').addEventListener('change', function(e) {
            const display = document.getElementById('fileNameDisplay');
            if (!e.target.files || !e.target.files[0]) {
                display.style.display = 'none';
                return;
            }
            const fileName = e.target.files[0].name;
            display.textContent = 'Secilen Dosya: ' + fileName;
            display.style.display = 'block';
        });
    </script>
</body>
</html>

