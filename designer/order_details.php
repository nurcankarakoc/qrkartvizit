<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/dynamic_form.php';

require_role_or_redirect($pdo, 'designer', '../auth/login.php');

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

$stmt = $pdo->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
     FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE o.id = ?
     LIMIT 1"
);
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: dashboard.php');
    exit();
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
    $env_app_url = trim((string) getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/designer/order_details.php');
    $project_path = preg_replace('#/designer/[^/]+$#', '', $script_name);
    return $scheme . '://' . $host . rtrim((string) $project_path, '/');
}

function build_dynamic_qr_url(string $target_url): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&ecc=M&data=' . rawurlencode($target_url);
}

$has_digital_profile = has_digital_profile_package((string)($order['package'] ?? ''));

$profile_order_col = df_table_has_column($pdo, 'profiles', 'created_at') ? 'created_at' : 'id';
$stmt_profile = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY {$profile_order_col} DESC LIMIT 1");
$stmt_profile->execute([(int)$order['user_id']]);
$profile = $stmt_profile->fetch(PDO::FETCH_ASSOC) ?: [];

$public_profile_url = '';
if (!empty($profile['slug'])) {
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

try {
    $stmt_drafts = $pdo->prepare("SELECT * FROM design_drafts WHERE order_id = ? ORDER BY created_at DESC");
    $stmt_drafts->execute([$order_id]);
    $drafts = $stmt_drafts->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $drafts = [];
}

// Durum geçişi: yalnızca POST + CSRF ile tetiklenir, GET'te otomatik değişmez
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'start_designing'
    && in_array((string)$order['status'], ['pending', 'pending_design'], true)
) {
    verify_csrf_or_redirect('order_details.php?id=' . $order_id . '&error=csrf');
    $pdo->prepare("UPDATE orders SET status = 'designing' WHERE id = ?")->execute([$order_id]);
    $order['status'] = 'designing';
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'send_to_printing'
    && (string)$order['status'] === 'approved'
) {
    verify_csrf_or_redirect('order_details.php?id=' . $order_id . '&error=csrf');
    // Sipariş durumunu güncelle
    $pdo->prepare("UPDATE orders SET status = 'printing' WHERE id = ?")->execute([$order_id]);
    // En son taslağın durumunu da 'printing' yap ki sayımlarda tutarlı olsun
    $pdo->prepare("UPDATE design_drafts SET status = 'printing' WHERE order_id = ? ORDER BY created_at DESC LIMIT 1")->execute([$order_id]);
    $order['status'] = 'printing';
}

$order_answers = df_get_order_answers($pdo, $order_id);

$upload_success = (($_GET['success'] ?? '') === 'uploaded');
$upload_error_key = (string)($_GET['error'] ?? '');
$upload_error_messages = [
    'csrf' => 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.',
    'file_type' => 'Sadece JPG, PNG, WEBP veya PDF dosyası yükleyebilirsiniz.',
    'file_mime' => 'Dosya türü doğrulanamadı. Dosyayı farklı bir formatta yeniden kaydedip deneyin.',
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

$xml_error_key = (string)($_GET['xml_error'] ?? '');
$xml_error_messages = [
    'invalid_order' => 'XML aktarımı için sipariş bilgisi bulunamadı.',
    'not_found' => 'Sipariş bulunamadı, XML üretilemedi.',
    'export_failed' => 'Illustrator XML dosyası üretilirken bir hata oluştu.',
];
$xml_error_message = $xml_error_messages[$xml_error_key] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı #<?php echo $order_id; ?> - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .order-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 1.2rem; margin-top: 1rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 1rem; box-shadow: 0 10px 22px rgba(15,23,42,0.04); }
        .upload-alert { margin-bottom: 0.8rem; padding: 0.85rem 0.95rem; border-radius: 12px; font-weight: 700; display: flex; align-items: center; gap: 0.65rem; }
        .upload-alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .upload-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .section-title { margin: 0 0 0.7rem; color: var(--navy-blue); font-size: 1.02rem; }
        .design-notes { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.8rem; line-height: 1.55; color: #334155; }
        .answers-list { margin-top: 0.75rem; display: grid; gap: 0.5rem; }
        .answers-item { border: 1px solid #e2e8f0; border-radius: 10px; padding: 0.55rem 0.65rem; }
        .answers-label { font-size: 0.74rem; color: #64748b; font-weight: 800; text-transform: uppercase; }
        .answers-value { margin-top: 0.2rem; font-size: 0.86rem; color: #0f172a; white-space: pre-wrap; word-break: break-word; }
        .logo-preview img, .qr-area img { max-width: min(180px, 100%); height: auto; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem; background: #fff; display: block; margin: 0.8rem auto; }
        .qr-area { text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.85rem; }
        .qr-links { display: grid; gap: 0.45rem; margin-top: 0.5rem; }
        .qr-links a { text-decoration: none; font-weight: 800; color: var(--gold); font-size: 0.86rem; }
        .upload-zone { margin-top: 1rem; border: 2px dashed #cbd5e1; border-radius: 14px; padding: 1.35rem 1rem; text-align: center; background: #f8fafc; position: relative; }
        .upload-zone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .btn-submit-draft { width: 100%; margin-top: 0.8rem; border: none; border-radius: 12px; padding: 0.85rem 1rem; font-weight: 800; background: var(--navy-blue); color: #fff; cursor: pointer; }
        .info-row { display: flex; justify-content: space-between; gap: 0.6rem; margin-bottom: 0.55rem; font-size: 0.86rem; }
        .info-row span:first-child { color: #64748b; }
        .info-row span:last-child { text-align: right; color: #0f172a; font-weight: 700; }
        .draft-item { display: flex; align-items: center; gap: 0.6rem; border: 1px solid #eef2f6; border-radius: 10px; padding: 0.55rem; margin-bottom: 0.45rem; background: #fff; }
        .draft-item img { width: 54px; height: 54px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
        @media (max-width: 1024px) { .order-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .btn-submit-draft, .btn-action { min-height: 44px; }
            .top-bar { gap: 0.6rem; }
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
                    <li><a href="designs.php"><i data-lucide="image"></i> Tasarımlar</a></li>
                    <li><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
                    <li><a href="form-control.php"><i data-lucide="sliders-horizontal"></i> Form Kontrol Merkezi</a></li>
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
                    <i data-lucide="arrow-left" style="width:16px; margin-right:0.35rem; vertical-align:middle;"></i> Geri Dön
                </a>
                <div class="order-id-badge" style="font-weight:800; color:var(--navy-blue);">Sipariş ID: #<?php echo $order_id; ?></div>
            </div>

            <div class="content-wrapper">
                <?php if ($upload_success): ?>
                    <div class="upload-alert success"><i data-lucide="check-circle"></i> Taslak başarıyla gönderildi.</div>
                <?php elseif ($upload_error_message !== ''): ?>
                    <div class="upload-alert error"><i data-lucide="alert-circle"></i> <?php echo htmlspecialchars($upload_error_message); ?></div>
                <?php endif; ?>
                <?php if ($xml_error_message !== ''): ?>
                    <div class="upload-alert error"><i data-lucide="file-warning"></i> <?php echo htmlspecialchars($xml_error_message); ?></div>
                <?php endif; ?>

                <div class="order-grid">
                    <section class="card">
                        <h1 class="section-title">Tasarım Detayları</h1>
                        <div class="design-notes">
                            <strong>Müşteri Notu:</strong><br>
                            <?php echo nl2br(htmlspecialchars((string)($order['design_notes'] ?: 'Müşteri tarafından not eklenmemiş.'))); ?>
                        </div>

                        <?php if (!empty($order_answers)): ?>
                            <div style="margin-top:0.9rem;">
                                <h2 class="section-title" style="font-size:0.95rem;">Form Cevapları</h2>
                                <div class="answers-list">
                                    <?php foreach ($order_answers as $answer): ?>
                                        <?php $answer_value = trim((string)($answer['value_text'] ?? '')); ?>
                                        <?php if ($answer_value === '') { continue; } ?>
                                        <div class="answers-item">
                                            <div class="answers-label"><?php echo htmlspecialchars((string)($answer['field_label'] ?? 'Alan')); ?></div>
                                            <div class="answers-value"><?php echo nl2br(htmlspecialchars($answer_value)); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order['logo_path'])): ?>
                            <div class="logo-preview" style="margin-top:1rem;">
                                <strong style="color:var(--navy-blue);">Kurumsal Logo</strong>
                                <img src="../<?php echo htmlspecialchars((string)$order['logo_path']); ?>" alt="Logo">
                                <a href="../<?php echo htmlspecialchars((string)$order['logo_path']); ?>" download class="btn-action" style="margin:0.3rem auto; display:table;">Orijinal Logoyu İndir</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_digital_profile): ?>
                            <div style="margin-top:1rem;">
                                <strong style="color:var(--navy-blue);">Dijital Profil ve QR</strong>
                                <div class="qr-area">
                                    <?php if ($resolved_qr_asset !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($resolved_qr_asset); ?>" alt="QR">
                                    <?php elseif ($public_profile_url !== ''): ?>
                                        <p style="margin:0.2rem 0 0.6rem; color:#334155;">QR henüz hazır değil, profil URL hazır:</p>
                                        <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" rel="noopener" style="font-size:0.82rem; word-break:break-all;"><?php echo htmlspecialchars($public_profile_url); ?></a>
                                    <?php else: ?>
                                        <p style="margin:0.2rem 0 0.6rem; color:#64748b;">Müşteri dijital profili henüz tamamlamadı.</p>
                                    <?php endif; ?>
                                    <div class="qr-links">
                                        <a href="../processes/designer_qr_download.php?order_id=<?php echo (int)$order_id; ?>"><i data-lucide="download"></i> Dinamik QR İndir</a>
                                        <a href="../processes/designer_xml_export.php?order_id=<?php echo (int)$order_id; ?>"><i data-lucide="file-code"></i> Illustrator XML İndir</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:1rem;">
                                <a href="../processes/designer_xml_export.php?order_id=<?php echo (int)$order_id; ?>" class="btn-action"><i data-lucide="file-code"></i> Illustrator XML İndir</a>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:1rem;">
                            <h2 class="section-title" style="font-size:0.95rem;">Taslak Yükle</h2>
                            <form action="../processes/designer_upload.php" method="POST" enctype="multipart/form-data">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <div class="upload-zone">
                                    <i data-lucide="upload-cloud"></i>
                                    <p style="margin:0.3rem 0 0;">Dosyayı sürükleyin veya tıklayın</p>
                                    <small>JPG, PNG, WEBP, PDF (maks 10MB)</small>
                                    <input type="file" name="draft_file" id="draftFile" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                                </div>
                                <div id="fileNameDisplay" style="display:none; margin-top:0.6rem; font-weight:700; color:var(--gold);"></div>
                                <button type="submit" class="btn-submit-draft">Taslağı Müşteri Onayına Sun</button>
                            </form>
                        </div>
                    </section>

                    <aside>
                        <div class="card">
                            <h3 class="section-title" style="font-size:0.95rem;">Müşteri Bilgileri</h3>
                            <div class="info-row"><span>Ad Soyad</span><span><?php echo htmlspecialchars((string)$order['customer_name']); ?></span></div>
                            <div class="info-row"><span>E-posta</span><span><?php echo htmlspecialchars((string)$order['customer_email']); ?></span></div>
                            <div class="info-row"><span>Telefon</span><span><?php echo htmlspecialchars((string)($order['customer_phone'] ?: 'Belirtilmemiş')); ?></span></div>
                        </div>

                        <div class="card" style="margin-top:0.8rem;">
                            <h3 class="section-title" style="font-size:0.95rem;">İş Detayları</h3>
                            <div class="info-row"><span>Paket</span><span><?php echo htmlspecialchars((string)($order['package'] ?? 'classic')); ?></span></div>
                            <div class="info-row"><span>Sipariş Tarihi</span><span><?php echo date('d.m.Y', strtotime((string)$order['created_at'])); ?></span></div>
                            <div class="info-row"><span>Durum</span>
                                <span style="text-transform: capitalize;">
                                    <?php 
                                        $os = (string)$order['status'];
                                        if($os === 'printing') echo 'Baskıda';
                                        elseif($os === 'approved') echo 'Müşteri Onayladı';
                                        elseif($os === 'awaiting_approval') echo 'Onay Bekliyor';
                                        elseif($os === 'revision_requested') echo 'Revize İstendi';
                                        elseif($os === 'designing') echo 'Tasarım Aşamasında';
                                        else echo $os;
                                    ?>
                                </span>
                            </div>
                            <div class="info-row"><span>Revize Hakkı</span><span><?php echo (int)($order['revision_count'] ?? 0); ?></span></div>
                        </div>

                        <?php if ((string)$order['status'] === 'approved'): ?>
                        <form method="POST" action="order_details.php?id=<?php echo $order_id; ?>" style="margin-top:0.75rem;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="send_to_printing">
                            <button type="submit" style="width:100%; padding:0.75rem; background:#ea580c; color:#fff; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:0.5rem;">
                                <i data-lucide="printer" style="width:18px;"></i> Baskıya Gönder
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (in_array((string)$order['status'], ['pending', 'pending_design'], true)): ?>
                        <form method="POST" action="order_details.php?id=<?php echo $order_id; ?>" style="margin-top:0.75rem;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="start_designing">
                            <button type="submit" style="width:100%; padding:0.75rem; background:#0A2F2F; color:#fff; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:0.9rem;">
                                Tasarıma Başla
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (!empty($drafts)): ?>
                            <div class="card" style="margin-top:0.8rem;">
                                <h3 class="section-title" style="font-size:0.95rem;">Yükleme Geçmişi</h3>
                                <?php foreach ($drafts as $draft): ?>
                                    <?php
                                        $draft_path = trim((string)($draft['file_path'] ?? ''));
                                        if ($draft_path === '') { continue; }
                                    ?>
                                    <div class="draft-item">
                                        <img src="../<?php echo htmlspecialchars($draft_path); ?>" alt="Draft">
                                        <div style="flex:1;">
                                            <div style="font-size:0.75rem; color:#64748b;"><?php echo date('d.m.Y H:i', strtotime((string)$draft['created_at'])); ?></div>
                                            <div style="font-size:0.78rem; font-weight:800; color:#0f172a;"><?php echo htmlspecialchars((string)$draft['status']); ?></div>
                                        </div>
                                        <button type="button" class="tiny-open" style="border:0; background:#f1f5f9; border-radius:8px; padding:0.35rem 0.5rem; cursor:pointer;" onclick="window.open('../<?php echo htmlspecialchars($draft_path); ?>', '_blank')">Aç</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
        const draftFile = document.getElementById('draftFile');
        if (draftFile) {
            draftFile.addEventListener('change', function(event) {
                const display = document.getElementById('fileNameDisplay');
                if (!display) return;
                if (!event.target.files || !event.target.files[0]) {
                    display.style.display = 'none';
                    return;
                }
                display.textContent = 'Seçilen dosya: ' + event.target.files[0].name;
                display.style.display = 'block';
            });
        }
    </script>
</body>
</html>
