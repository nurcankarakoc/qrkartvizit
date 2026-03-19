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
    'csrf' => 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.',
    'file_type' => 'Sadece JPG, PNG, WEBP veya PDF dosyası yükleyebilirsiniz.',
    'file_mime' => 'Dosya türü doğrulanamadı. Farklı bir formatta tekrar kaydedip deneyin.',
    'file_size' => 'Dosya boyutu 10MB sınırını aşıyor.',
    'upload_ini_size' => 'Sunucu dosya limiti aşıldı. Dosyayı küçültüp tekrar deneyin.',
    'upload_form_size' => 'Dosya form limitini aştı.',
    'upload_partial' => 'Dosya eksik yüklendi. İnternet bağlantınızı kontrol edin.',
    'upload_no_file' => 'Lütfen önce bir dosya seçin.',
    'upload_tmp_dir' => 'Sunucuda geçici klasör bulunamadı.',
    'upload_cant_write' => 'Dosya diske yazılamadı.',
    'upload_extension' => 'Yükleme bir PHP eklentisi tarafından durduruldu.',
    'upload_unknown' => 'Dosya yüklenirken bilinmeyen bir hata oluştu.',
    'upload_dir' => 'Yükleme klasörü oluşturulamadı.',
    'move' => 'Seçilen dosya hedef klasöre taşınamadı.',
    'db' => 'Dosya yüklendi ancak veritabanı kaydı yapılamadı.',
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
            border-radius: 28px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px rgba(10, 47, 47, 0.03); 
        }
        .card-sidebar-info { display: flex; flex-direction: column; gap: 2rem; }
        .info-box { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 2rem; 
            border-radius: 28px; 
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px rgba(10, 47, 47, 0.03); 
        }
        .info-box h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--navy-blue); }
        .info-box h3 i { color: var(--gold); width: 22px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.95rem; }
        .detail-row span:first-child { color: #64748b; font-weight: 500; }
        .detail-row span:last-child { color: #1e293b; font-weight: 700; }
        .design-notes { background: #f8fafc; padding: 1.5rem; border-radius: 20px; border-left: 5px solid var(--gold); margin-top: 2rem; line-height: 1.6; color: #475569; font-size: 0.95rem; }
        
        .logo-preview img, .qr-area img { 
            max-width: min(180px, 100%); 
            height: auto; 
            border-radius: 16px; 
            border: 1.5px solid rgba(166, 128, 63, 0.25); 
            padding: 1.25rem; 
            background: #fff;
            transition: var(--transition-smooth);
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            display: block;
            margin: 1.5rem auto;
        }
        .logo-preview img:hover, .qr-area img:hover {
            border-color: var(--gold);
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 40px rgba(166, 128, 63, 0.15);
        }

        .upload-zone { margin-top: 3rem; border: 2.5px dashed #cbd5e1; padding: 4rem 2rem; border-radius: 28px; text-align: center; transition: var(--transition-smooth); cursor: pointer; position: relative; background: #fafbfc; }
        .upload-zone:hover { border-color: var(--gold); background: #fffdf5; transform: scale(1.01); }
        .upload-zone input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-zone i { font-size: 3.5rem; color: #94a3b8; display: block; margin: 0 auto 1.5rem; opacity: 0.6; }
        .upload-zone p { font-weight: 800; color: #475569; font-size: 1.1rem; }
        
        .btn-submit-draft { width: 100%; padding: 1.25rem; background: var(--navy-blue); color: #fff; border: none; border-radius: 18px; font-weight: 800; font-size: 1.05rem; cursor: pointer; margin-top: 2rem; transition: var(--transition-smooth); box-shadow: 0 10px 25px rgba(10,47,47,0.15); }
        .btn-submit-draft:hover { background: var(--navy-dark); transform: translateY(-4px); box-shadow: 0 15px 40px rgba(10,47,47,0.25); }
        
        .qr-area { text-align: center; padding: 2rem; background: #f8fafc; border-radius: 24px; margin-top: 1rem; border: 1px solid #edf2f7; }
        .qr-area a { font-size: 0.9rem; font-weight: 800; color: var(--gold); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.6rem; transition: var(--transition-smooth); }
        .qr-area a:hover { transform: translateY(-2px); color: var(--gold-light); }
        
        .draft-history { margin-top: 4rem; }
        .draft-card { display: flex; align-items: center; gap: 1.5rem; padding: 1.5rem; background: #fff; border-radius: 20px; margin-bottom: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 4px 12px rgba(0,0,0,0.02); transition: var(--transition-smooth); }
        .draft-card:hover { transform: translateX(8px); border-color: var(--gold); box-shadow: 0 8px 25px rgba(10,47,47,0.05); }
        
        .draft-thumb { width: 90px; height: 90px; background: #f1f5f9; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; }
        .draft-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .draft-info { flex-grow: 1; }
        .draft-status.approved { color: #10b981; font-weight: 800; display: flex; align-items: center; gap: 0.35rem; }
        .draft-status.pending { color: var(--navy-blue); font-weight: 800; display: flex; align-items: center; gap: 0.35rem; }
        
        .status-chip { padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.8rem; font-weight: 800; background: rgba(10,47,47,0.05); color: var(--navy-blue); text-transform: uppercase; letter-spacing: 0.5px; }
        .upload-alert { margin-top: 1rem; margin-bottom: 2rem; padding: 1.25rem; border-radius: 18px; font-weight: 700; display: flex; align-items: center; gap: 1rem; }
        .upload-alert.success { background: #ecfdf5; color: #064e3b; border: 1.5px solid #10b981; }
        .upload-alert.error { background: #fef2f2; color: #7f1d1d; border: 1.5px solid #ef4444; }

        @media (max-width: 1024px) {
            .order-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .card-details, .info-box { padding: 1.5rem; border-radius: 24px; }
            .upload-zone { padding: 3rem 1.5rem; }
            .btn-submit-draft, .btn-action { min-height: 48px; }
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
                <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <a href="dashboard.php" class="btn-action">
                    <i data-lucide="arrow-left" style="width: 16px; margin-right: 0.5rem; vertical-align: middle;"></i> Geri Dön
                </a>
                <div class="order-id-badge" style="font-weight: 800; color: var(--navy-blue); font-size: 1.1rem;">Sipariş ID: #<?php echo $order_id; ?></div>
            </div>

            <div class="content-wrapper">
                <?php if ($upload_success): ?>
                    <div class="upload-alert success">
                        <i data-lucide="check-circle"></i>
                        Taslak başarıyla gönderildi. Müşteri panelinde onaya düştü.
                    </div>
                <?php elseif ($upload_error_message !== ''): ?>
                    <div class="upload-alert error">
                        <i data-lucide="alert-circle"></i>
                        <?php echo htmlspecialchars($upload_error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="order-grid">
                    <div class="main-pane">
                        <div class="card-details">
                            <h1 style="color: var(--navy-blue); margin-bottom: 2.5rem; letter-spacing: -1px; font-weight: 800;">Tasarım Detayları</h1>
                            
                            <div class="design-notes">
                                <strong style="color: var(--navy-blue); font-size: 0.9rem; margin-bottom: 0.5rem; display: block; text-transform: uppercase; letter-spacing: 1px;">Müşteri Notu:</strong>
                                <?php echo nl2br(htmlspecialchars($order['design_notes'] ?: 'Müşteri tarafından özel bir not eklenmemiştir.')); ?>
                            </div>

                            <?php if ($order['logo_path']): ?>
                                <div class="logo-preview" style="margin-top: 3.5rem;">
                                    <strong style="color: var(--navy-blue); display: block; margin-bottom: 1.5rem; font-weight: 800; font-size: 1.1rem;">Kurumsal Logo:</strong>
                                    <img src="../<?php echo $order['logo_path']; ?>" alt="Logo">
                                    <a href="../<?php echo $order['logo_path']; ?>" download class="btn-action" style="margin: 0.5rem auto; width: fit-content; background: #fff; border: 1.5px solid #e2e8f0; padding: 0.6rem 1.4rem; border-radius: 12px; font-weight: 800; color: var(--navy-blue);"><i data-lucide="download"></i> Orijinal Logoyu İndir</a>
                                </div>
                            <?php endif; ?>

                            <?php if ($has_digital_profile): ?>
                                <div style="margin-top: 4rem;">
                                    <strong style="color: var(--navy-blue); display: block; margin-bottom: 1.5rem; font-weight: 800; font-size: 1.1rem;">Dijital Profil Preview & QR:</strong>
                                    <div class="qr-area">
                                        <?php if ($resolved_qr_asset !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($resolved_qr_asset); ?>" alt="QR Code">
                                            <a href="../processes/designer_qr_download.php?order_id=<?php echo (int)$order_id; ?>"><i data-lucide="download"></i> Dinamik QR'ı İndir</a>
                                        <?php elseif ($public_profile_url !== ''): ?>
                                            <div style="padding: 1.5rem 1rem; color: #334155;">
                                                <i data-lucide="link-2" style="margin-bottom: 1rem; width: 32px; height: 32px; color: var(--gold); opacity: 0.5;"></i>
                                                <p style="margin: 0 0 1rem; font-weight: 800; font-size: 1.05rem;">Profil linki oluşturuldu ancak QR henüz sisteme düşmedi.</p>
                                                <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" rel="noopener" style="word-break: break-all; font-size: 0.9rem; color: var(--navy-blue); background: #fff; padding: 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px; display: block; margin-bottom: 1rem;">
                                                    <?php echo htmlspecialchars($public_profile_url); ?>
                                                </a>
                                                <a href="../processes/designer_qr_download.php?order_id=<?php echo (int)$order_id; ?>" style="margin-top:0.7rem; font-weight:800; color: var(--gold);">
                                                    <i data-lucide="download"></i> QR Kodunu Oluştur ve İndir
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div style="padding: 2.5rem; color: #94a3b8;"><i data-lucide="alert-triangle" style="width: 32px; height: 32px; margin-bottom: 1rem;"></i><br>Dijital profil henüz müşteri tarafından yapılandırılmamış.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="upload-section">
                                <h2 style="margin-top: 4rem; margin-bottom: 1.5rem; color: var(--navy-dark); font-weight: 800; letter-spacing: -0.5px;">Hazırlanan Taslağı Yükle</h2>
                                <form action="../processes/designer_upload.php" method="POST" enctype="multipart/form-data">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    <div class="upload-zone" id="uploadZone">
                                        <i data-lucide="upload-cloud"></i>
                                        <p>Tasarım Taslağını Buraya Sürükleyin veya Tıklayın</p>
                                        <span style="display: block; margin-top: 0.5rem; color: #94a3b8; font-size: 0.85rem;">Desteklenen formatlar: JPG, PNG, WEBP, PDF (Max 10MB)</span>
                                        <input type="file" name="draft_file" id="draftFile" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                                    </div>
                                    <div id="fileNameDisplay" style="margin-top: 1.5rem; font-weight: 800; color: var(--gold); background: rgba(166,128,63,0.05); padding: 1rem; border-radius: 12px; display: none; text-align: center; border: 1px dashed var(--gold);"></div>
                                    <button type="submit" class="btn-submit-draft">Taslağı Müşteri Onayına Sun</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-pane">
                        <div class="card-sidebar-info">
                            <div class="info-box">
                                <h3><i data-lucide="user"></i> Müşteri Bilgileri</h3>
                                <div class="detail-row"><span>Ad Soyad:</span><span><?php echo htmlspecialchars($order['customer_name']); ?></span></div>
                                <div class="detail-row"><span>Email:</span><span style="font-size: 0.85rem;"><?php echo htmlspecialchars($order['customer_email']); ?></span></div>
                                <div class="detail-row"><span>Telefon:</span><span><?php echo htmlspecialchars($order['customer_phone'] ?: 'Belirtilmemiş'); ?></span></div>
                            </div>
                            <div class="info-box">
                                <h3><i data-lucide="package"></i> İş Detayları</h3>
                                <div class="detail-row"><span>Seçilen Paket:</span><span style="color: var(--gold);"><?php echo htmlspecialchars($order['package'] ?? 'classic'); ?></span></div>
                                <div class="detail-row"><span>Sipariş Tarihi:</span><span><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></span></div>
                                <div class="detail-row"><span>Güncel Durum:</span><span class="status-chip"><?php echo htmlspecialchars($order['status']); ?></span></div>
                                <div class="detail-row"><span>Revize Hakkı:</span><span style="background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 6px;"><?php echo (int)($order['revision_count'] ?? 0); ?></span></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($drafts)): ?>
                        <div class="draft-history" style="margin-top: 2rem;">
                            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; color: var(--navy-blue);">Yükleme Geçmişi</h3>
                            <?php foreach ($drafts as $draft): ?>
                                <div class="draft-card" style="display: flex; align-items: center; gap: 1rem; background: #fff; padding: 1rem; border-radius: 16px; border: 1px solid #eef2f6; margin-bottom: 1rem;">
                                    <div class="draft-thumb" style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="window.open('../<?php echo htmlspecialchars($draft['file_path']); ?>', '_blank')">
                                        <img src="../<?php echo htmlspecialchars($draft['file_path']); ?>" alt="Draft" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="draft-info" style="flex: 1;">
                                        <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;"><?php echo date('d.m.Y H:i', strtotime($draft['created_at'])); ?></div>
                                        <div class="draft-status <?php echo (string)$draft['status'] === 'approved' ? 'approved' : 'pending'; ?>" style="font-weight: 800; font-size: 0.8rem; margin-top: 0.25rem;">
                                            <?php if((string)$draft['status'] === 'approved'): ?>
                                                <i data-lucide="check-circle" style="width:12px;"></i> Onaylandı
                                            <?php else: ?>
                                                <i data-lucide="clock" style="width:12px;"></i> Beklemede
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="button" onclick="window.open('../<?php echo htmlspecialchars($draft['file_path']); ?>', '_blank')" style="background: #f1f5f9; color: #475569; border: none; padding: 0.4rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.75rem; cursor: pointer;">Aç</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
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
            display.textContent = '📦 Seçilen Dosya: ' + fileName;
            display.style.display = 'block';
        });
    </script>
</body>
</html>
