<?php
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';

ensure_session_started();

require_role_or_redirect($pdo, 'admin', '../auth/login.php');

$packages = qrk_get_all_package_definitions($pdo);
$message_key = trim((string)($_GET['msg'] ?? ''));
$message_map = [
    'updated' => ['type' => 'success', 'text' => 'Paket içeriği güncellendi.'],
    'invalid' => ['type' => 'error', 'text' => 'Geçersiz paket isteği alındı.'],
    'error' => ['type' => 'error', 'text' => 'Paket kaydedilirken bir hata oluştu.'],
];
$flash = $message_map[$message_key] ?? null;

if (!function_exists('admin_package_lines')) {
    function admin_package_lines(array $items): string
    {
        return implode("\n", array_map(static fn($item): string => trim((string)$item), $items));
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Yönetimi - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .flash { margin-bottom: 1rem; border-radius: 14px; padding: 0.9rem 1rem; font-size: 0.92rem; font-weight: 700; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .package-editor-grid { display: grid; gap: 1.5rem; }
        .package-editor-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,250,252,0.96));
            border: 1px solid rgba(166, 128, 63, 0.18);
            border-radius: 26px;
            padding: 1.5rem;
            box-shadow: 0 18px 40px rgba(10, 47, 47, 0.08);
        }
        .package-editor-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }
        .package-editor-title { margin: 0; color: var(--navy-blue); font-size: 1.3rem; }
        .package-editor-subtitle { margin: 0.35rem 0 0; color: var(--text-muted); font-size: 0.92rem; }
        .package-slug {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            background: rgba(10, 47, 47, 0.08);
            color: var(--navy-blue);
            font-size: 0.8rem;
            font-weight: 800;
        }
        .package-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .package-form-group { display: flex; flex-direction: column; gap: 0.45rem; }
        .package-form-group.full { grid-column: 1 / -1; }
        .package-form-group label { color: var(--navy-blue); font-weight: 700; font-size: 0.88rem; }
        .package-form-group input,
        .package-form-group textarea,
        .package-form-group select {
            width: 100%;
            border-radius: 18px;
            border: 1px solid #d9e2ec;
            background: #fff;
            padding: 0.9rem 1rem;
            font: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .package-form-group textarea { min-height: 118px; resize: vertical; }
        .package-form-group input:focus,
        .package-form-group textarea:focus,
        .package-form-group select:focus {
            outline: none;
            border-color: rgba(166, 128, 63, 0.85);
            box-shadow: 0 0 0 4px rgba(166, 128, 63, 0.14);
        }
        .package-form-help { color: #64748b; font-size: 0.78rem; line-height: 1.5; }
        .package-toggles { display: flex; flex-wrap: wrap; gap: 1rem; }
        .package-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            border-radius: 999px;
            padding: 0.7rem 1rem;
            background: rgba(10, 47, 47, 0.04);
            border: 1px solid rgba(10, 47, 47, 0.08);
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--navy-blue);
        }
        .package-toggle input { width: auto; margin: 0; }
        .package-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.25rem;
        }
        .save-btn {
            border: none;
            border-radius: 999px;
            padding: 0.95rem 1.4rem;
            background: linear-gradient(135deg, #0A2F2F, #145959);
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(10, 47, 47, 0.18);
        }
        @media (max-width: 900px) {
            .package-form-grid { grid-template-columns: 1fr; }
            .package-editor-header { flex-direction: column; }
            .package-actions { justify-content: stretch; }
            .save-btn { width: 100%; }
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
                    <li><a href="form-approvals.php"><i data-lucide="clipboard-check"></i> Form Onayları</a></li>
                    <li class="active"><a href="packages.php"><i data-lucide="package-2"></i> Paketler</a></li>
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
                <h1>Paket İçeriği Yönetimi</h1>
            </header>

            <div class="content-wrapper">
                <?php if ($flash): ?>
                    <div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
                <?php endif; ?>

                <div class="package-editor-grid">
                    <?php foreach ($packages as $slug => $package): ?>
                        <section class="package-editor-card">
                            <div class="package-editor-header">
                                <div>
                                    <h2 class="package-editor-title"><?php echo htmlspecialchars((string)$package['label']); ?></h2>
                                    <p class="package-editor-subtitle">Kayıt ekranındaki kart metinleri, müşteri panelindeki paket özeti ve içerik listeleri buradan yönetilir.</p>
                                </div>
                                <span class="package-slug"><?php echo htmlspecialchars($slug); ?></span>
                            </div>

                            <form method="POST" action="../processes/admin_package_update.php">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">

                                <div class="package-form-grid">
                                    <div class="package-form-group">
                                        <label>Panel Etiketi</label>
                                        <input type="text" name="label" value="<?php echo htmlspecialchars((string)$package['label']); ?>" maxlength="100">
                                    </div>
                                    <div class="package-form-group">
                                        <label>Kısa Etiket</label>
                                        <input type="text" name="short_label" value="<?php echo htmlspecialchars((string)$package['short_label']); ?>" maxlength="60">
                                    </div>

                                    <div class="package-form-group">
                                        <label>Gerçek Fiyat</label>
                                        <input type="number" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float)$package['price'], 2, '.', '')); ?>">
                                    </div>
                                    <div class="package-form-group">
                                        <label>Kayıt Ekranı Fiyat Yazısı</label>
                                        <input type="text" name="register_price_text" value="<?php echo htmlspecialchars((string)$package['register_price_text']); ?>" maxlength="120">
                                        <span class="package-form-help">Örn: `1.200 ₺ (İlk Ay)` veya `700 ₺/ay`</span>
                                    </div>

                                    <div class="package-form-group">
                                        <label>Revize Hakkı</label>
                                        <input type="number" name="included_revisions" min="0" step="1" value="<?php echo (int)$package['included_revisions']; ?>">
                                    </div>
                                    <div class="package-form-group">
                                        <label>Kayıt Ekranı Rozeti</label>
                                        <input type="text" name="register_badge" value="<?php echo htmlspecialchars((string)$package['register_badge']); ?>" maxlength="120">
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Paket Açıklaması</label>
                                        <textarea name="description"><?php echo htmlspecialchars((string)$package['description']); ?></textarea>
                                    </div>

                                    <div class="package-form-group">
                                        <label>Kayıt Ekranı Başlığı</label>
                                        <input type="text" name="register_title" value="<?php echo htmlspecialchars((string)$package['register_title']); ?>" maxlength="150">
                                    </div>
                                    <div class="package-form-group">
                                        <label>Kayıt Ekranı Alt Başlığı</label>
                                        <input type="text" name="register_subtitle" value="<?php echo htmlspecialchars((string)$package['register_subtitle']); ?>" maxlength="255">
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Kayıt Ekranı Notu</label>
                                        <textarea name="register_note"><?php echo htmlspecialchars((string)$package['register_note']); ?></textarea>
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Dijital Panel Durum Açıklaması</label>
                                        <textarea name="register_panel_text"><?php echo htmlspecialchars((string)$package['register_panel_text']); ?></textarea>
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Kayıt Ekranı Madde Listesi</label>
                                        <textarea name="register_features"><?php echo htmlspecialchars(admin_package_lines((array)$package['register_features'])); ?></textarea>
                                        <span class="package-form-help">Her satıra bir özellik yazın.</span>
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Dahil Olan Özellikler</label>
                                        <textarea name="included_features"><?php echo htmlspecialchars(admin_package_lines((array)$package['included_features'])); ?></textarea>
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Hariç Tutulan Özellikler</label>
                                        <textarea name="excluded_features"><?php echo htmlspecialchars(admin_package_lines((array)$package['excluded_features'])); ?></textarea>
                                    </div>

                                    <div class="package-form-group full">
                                        <label>Paket Ayarları</label>
                                        <div class="package-toggles">
                                            <label class="package-toggle"><input type="checkbox" name="is_active" value="1" <?php echo !empty($package['is_active']) ? 'checked' : ''; ?>> Kayıtta görünsün</label>
                                            <label class="package-toggle"><input type="checkbox" name="has_digital_profile" value="1" <?php echo !empty($package['has_digital_profile']) ? 'checked' : ''; ?>> Dijital profil açık</label>
                                            <label class="package-toggle"><input type="checkbox" name="has_physical_print" value="1" <?php echo !empty($package['has_physical_print']) ? 'checked' : ''; ?>> Fiziksel baskı açık</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="package-actions">
                                    <button type="submit" class="save-btn">Paketi Kaydet</button>
                                </div>
                            </form>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
