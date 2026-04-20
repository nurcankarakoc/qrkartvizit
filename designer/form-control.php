<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/dynamic_form.php';

require_role_or_redirect($pdo, 'designer', '../auth/login.php');

df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

$designer_id = (int) ($_SESSION['user_id'] ?? 0);
$fields = df_get_form_fields($pdo, true);

$pending_stmt = $pdo->query("SELECT COUNT(*) FROM form_change_requests WHERE status = 'pending'");
$pending_count = (int) $pending_stmt->fetchColumn();

$my_requests_stmt = $pdo->prepare(
    "SELECT * FROM form_change_requests
     WHERE requested_by = ?
     ORDER BY created_at DESC
     LIMIT 30"
);
$my_requests_stmt->execute([$designer_id]);
$my_requests = $my_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

$msg_key = trim((string) ($_GET['msg'] ?? ''));
$msg_map = [
    'csrf' => ['type' => 'error', 'text' => 'Güvenlik doğrulaması başarısız oldu.'],
    'field_updated' => ['type' => 'success', 'text' => 'Alan durumu güncellendi.'],
    'option_updated' => ['type' => 'success', 'text' => 'Seçenek durumu güncellendi.'],
    'request_sent' => ['type' => 'success', 'text' => 'Talep admin onayına gönderildi.'],
    'invalid' => ['type' => 'error', 'text' => 'Geçersiz işlem.'],
    'error' => ['type' => 'error', 'text' => 'İşlem sırasında beklenmeyen bir hata oluştu.'],
];
$flash = $msg_map[$msg_key] ?? null;

function req_status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Onaylandı',
        'rejected' => 'Reddedildi',
        default => 'Bekliyor',
    };
}

function req_status_class(string $status): string
{
    return match ($status) {
        'approved' => 'req-approved',
        'rejected' => 'req-rejected',
        default => 'req-pending',
    };
}

function req_type_label(string $type): string
{
    return match ($type) {
        'field_create' => 'Yeni Alan',
        'option_create' => 'Yeni Seçenek',
        default => 'Diğer',
    };
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Kontrol Merkezi - Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .flash { margin-bottom: 1rem; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.9rem; font-weight: 700; }
        .flash.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .layout-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 1.2rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1rem; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04); margin-bottom: 1rem; }
        .field-row {
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 0.75rem;
            margin-bottom: 0.65rem;
            cursor: pointer;
            transition: all 0.22s ease;
            background: #fff;
        }
        .field-row:last-child { margin-bottom: 0; }
        .field-row:hover,
        .field-row:focus-within {
            border-color: rgba(166, 128, 63, 0.55);
            box-shadow: 0 6px 16px rgba(166, 128, 63, 0.12);
            background: #fffdf8;
        }
        .field-row.is-selected {
            border-color: #A6803F;
            background: linear-gradient(135deg, rgba(166, 128, 63, 0.12), rgba(255, 255, 255, 0.98));
            box-shadow: 0 10px 22px rgba(166, 128, 63, 0.2);
        }
        .inline-form { display: flex; gap: 0.55rem; flex-wrap: wrap; align-items: center; }
        .pill { border-radius: 999px; padding: 0.2rem 0.6rem; font-size: 0.72rem; font-weight: 800; }
        .pill.active { color: #14532d; background: #dcfce7; border: 1px solid #bbf7d0; }
        .pill.passive { color: #7f1d1d; background: #fee2e2; border: 1px solid #fecaca; }
        .tiny-btn {
            border: 1px solid #dbe3ee;
            background: #fff;
            color: #0f172a;
            border-radius: 9px;
            padding: 0.45rem 0.7rem;
            font-size: 0.76rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tiny-btn.primary { background: #0A2F2F; color: #fff; border-color: #0A2F2F; }
        .tiny-btn.warn { border-color: #fecaca; color: #991b1b; background: #fff; }
        .tiny-btn:hover,
        .tiny-btn:focus-visible {
            border-color: #A6803F;
            box-shadow: 0 6px 14px rgba(166, 128, 63, 0.18);
            transform: translateY(-1px);
            outline: none;
        }
        .form-control { width: 100%; border: 1px solid #dbe3ee; border-radius: 10px; padding: 0.62rem 0.75rem; font-size: 0.85rem; min-width: 0; }
        .form-control::placeholder { font-size: 0.84rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; }
        .option-list { margin-top: 0.6rem; display: grid; gap: 0.45rem; }
        .option-item {
            border: 1px dashed #dbe3ee;
            border-radius: 10px;
            padding: 0.55rem 0.65rem;
            display: flex;
            justify-content: space-between;
            gap: 0.6rem;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }
        .option-item:hover,
        .option-item:focus-within {
            border-color: rgba(166, 128, 63, 0.6);
            background: #fffbf2;
        }
        .option-item.is-selected {
            border-style: solid;
            border-color: #A6803F;
            background: #fff8e8;
            box-shadow: inset 0 0 0 1px rgba(166, 128, 63, 0.18);
        }
        .req-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .req-table th, .req-table td { padding: 0.6rem; border-bottom: 1px solid #f1f5f9; text-align: left; font-size: 0.82rem; white-space: normal; word-break: break-word; }
        .req-pending { color: #9a3412; background: #fff7ed; border: 1px solid #fed7aa; }
        .req-approved { color: #166534; background: #ecfdf5; border: 1px solid #a7f3d0; }
        .req-rejected { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
        @media (max-width: 1024px) { .layout-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .inline-form .tiny-btn { width: 100%; min-height: 44px; }
            .req-table th, .req-table td { font-size: 0.78rem; padding: 0.5rem 0.4rem; }
            .form-control::placeholder { font-size: 0.78rem; }
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
                    <li><a href="designs.php"><i data-lucide="image"></i> Tasarımlarım</a></li>
                    <li><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
                    <li class="active"><a href="form-control.php"><i data-lucide="sliders-horizontal"></i> Form Kontrol Merkezi</a></li>
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
                    <h1 style="margin:0;">Form Kontrol Merkezi</h1>
                    <p style="margin:0.35rem 0 0; color:#64748b;">Alanları aç/kapat, yeni alan ve seçenek taleplerini yönet.</p>
                </div>
                <div class="date-badge" style="background:#fff; border:1px solid #e2e8f0; padding:0.55rem 0.75rem; border-radius:10px; font-weight:700;">
                    Bekleyen Onay: <?php echo $pending_count; ?>
                </div>
            </header>

            <?php if ($flash): ?>
                <div class="flash <?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['text']); ?></div>
            <?php endif; ?>

            <div class="layout-grid">
                <section>
                    <article class="card">
                        <h2 style="margin:0 0 0.8rem; font-size:1.05rem;">Aktif/Pasif Alanlar</h2>
                        <?php foreach ($fields as $field): ?>
                            <?php
                                $field_id = (int) $field['id'];
                                $is_active = (int) ($field['is_active'] ?? 0) === 1;
                                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                            ?>
                            <div class="field-row">
                                <div style="display:flex; justify-content:space-between; gap:0.75rem; align-items:flex-start;">
                                    <div>
                                        <strong><?php echo htmlspecialchars((string) ($field['field_label'] ?? '')); ?></strong>
                                    </div>
                                    <span class="pill <?php echo $is_active ? 'active' : 'passive'; ?>">
                                        <?php echo $is_active ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </div>

                                <form action="../processes/designer_form_control.php" method="POST" class="inline-form" style="margin-top:0.6rem;">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="toggle_field">
                                    <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
                                    <input type="hidden" name="is_active" value="<?php echo $is_active ? 0 : 1; ?>">
                                    <button type="submit" class="tiny-btn <?php echo $is_active ? 'warn' : 'primary'; ?>">
                                        <?php echo $is_active ? 'Pasife Al' : 'Aktif Et'; ?>
                                    </button>
                                </form>

                                <?php if ((string) ($field['field_type'] ?? '') === 'select'): ?>
                                    <div class="option-list">
                                        <?php foreach ($options as $option): ?>
                                            <?php $opt_active = (int) ($option['is_active'] ?? 0) === 1; ?>
                                            <div class="option-item">
                                                <div>
                                                    <strong style="font-size:0.8rem;"><?php echo htmlspecialchars((string) ($option['option_label'] ?? '')); ?></strong>
                                                </div>
                                                <form action="../processes/designer_form_control.php" method="POST" class="inline-form">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="toggle_option">
                                                    <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $opt_active ? 0 : 1; ?>">
                                                    <button type="submit" class="tiny-btn <?php echo $opt_active ? 'warn' : 'primary'; ?>">
                                                        <?php echo $opt_active ? 'Pasife Al' : 'Aktif Et'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>

                                        <form action="../processes/designer_form_control.php" method="POST" class="form-grid" style="margin-top:0.5rem;">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="request_new_option">
                                            <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
                                            <input type="text" name="option_label" class="form-control" placeholder="Yeni seçenek etiketi" required>
                                            <input type="text" name="option_value" class="form-control" placeholder="Opsiyonel kod">
                                            <button type="submit" class="tiny-btn primary" style="grid-column:1/-1;">Yeni Seçenek Talebi Gönder</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </section>

                <aside>
                    <article class="card">
                        <h2 style="margin:0 0 0.8rem; font-size:1.05rem;">Yeni Alan Talebi</h2>
                        <form action="../processes/designer_form_control.php" method="POST" class="form-grid">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="request_new_field">
                            <input type="text" name="field_label" class="form-control" placeholder="Alan başlığı" required>
                            <select name="field_type" class="form-control" required>
                                <option value="text">Kısa Metin</option>
                                <option value="textarea">Uzun Metin</option>
                                <option value="select">Açılır Liste</option>
                                <option value="email">E-posta</option>
                                <option value="url">Web Adresi</option>
                                <option value="tel">Telefon</option>
                                <option value="number">Sayı</option>
                            </select>
                            <input type="text" name="default_value" class="form-control" placeholder="Varsayılan değer">
                            <input type="text" name="placeholder" class="form-control" placeholder="Placeholder">
                            <input type="text" name="help_text" class="form-control" placeholder="Yardım metni">
                            <input type="text" name="show_on_packages" class="form-control" placeholder="Görünecek paketler">
                            <input type="text" name="required_on_packages" class="form-control" placeholder="Zorunlu paketler">
                            <div style="grid-column:1/-1; font-size:0.74rem; color:#64748b; margin-top:-0.15rem;">Paket formatı: <code>classic,smart,panel</code></div>
                            <label style="grid-column:1/-1; display:flex; gap:0.45rem; align-items:center; font-size:0.82rem;">
                                <input type="checkbox" name="is_required" value="1"> Paket bağımsız her durumda zorunlu olsun
                            </label>
                            <button type="submit" class="tiny-btn primary" style="grid-column:1/-1;">Admin Onayına Gönder</button>
                        </form>
                    </article>

                    <article class="card">
                        <h2 style="margin:0 0 0.8rem; font-size:1.05rem;">Talep Geçmişim</h2>
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>Tip</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_requests)): ?>
                                    <tr><td colspan="3" style="color:#64748b;">Henüz talep oluşturulmadı.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($my_requests as $req): ?>
                                        <?php $status = (string) ($req['status'] ?? 'pending'); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(req_type_label((string) ($req['request_type'] ?? ''))); ?></td>
                                            <td>
                                                <span class="pill <?php echo req_status_class($status); ?>">
                                                    <?php echo htmlspecialchars(req_status_label($status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $req['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </article>
                </aside>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();

        (function () {
            const fieldRows = Array.from(document.querySelectorAll('.field-row'));
            const optionItems = Array.from(document.querySelectorAll('.option-item'));

            function clearSelection(elements, className) {
                elements.forEach((el) => el.classList.remove(className));
            }

            fieldRows.forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('button, a')) {
                        return;
                    }
                    clearSelection(fieldRows, 'is-selected');
                    row.classList.add('is-selected');
                });
            });

            optionItems.forEach((item) => {
                item.addEventListener('click', (event) => {
                    if (event.target.closest('button, a')) {
                        return;
                    }
                    clearSelection(optionItems, 'is-selected');
                    item.classList.add('is-selected');
                });
            });

            document.querySelectorAll('.field-row form, .option-item form').forEach((formEl) => {
                formEl.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            });
        })();
    </script>
</body>
</html>
