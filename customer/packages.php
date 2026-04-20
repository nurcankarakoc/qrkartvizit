<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Müşteri'];
$user_name = trim((string)($user['name'] ?? 'Müşteri'));
if ($user_name === '') {
    $user_name = 'Müşteri';
}

$package_state = qrk_get_customer_package_state($pdo, $user_id);
$current_package_slug = (string)($package_state['package_slug'] ?? '');
$pending_package_slug = (string)($package_state['pending_package_slug'] ?? '');
$has_active_package = $current_package_slug !== '';
$can_change_package = !((bool)($package_state['has_existing_order'] ?? false));

$all_package_definitions = qrk_get_all_package_definitions($pdo);
$active_package_definitions = array_filter(
    $all_package_definitions,
    static fn(array $package): bool => (bool)($package['is_active'] ?? true)
);

if ($active_package_definitions === []) {
    $active_package_definitions = $all_package_definitions;
}

$status_key = trim((string)($_GET['status'] ?? ''));
$status_map = [
    'registered' => 'Başvurunuz alındı. Şimdi size uygun paketi seçerek devam edebilirsiniz.',
    'selected' => 'Paket seçiminiz kaydedildi. Şimdi satın alma adımına geçebilirsiniz.',
    'preview_selected' => 'Paket seçiminiz kaydedildi. Akış doğrudan satın alma adımına ilerliyor.',
    'invalid' => 'Seçilen paket geçersiz veya şu an aktif değil.',
    'locked' => 'Aktif siparişiniz olduğu için paket değişikliği kapalı. Destek ekibiyle iletişime geçebilirsiniz.',
    'csrf' => 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.',
];
$status_message = $status_map[$status_key] ?? '';
$status_is_error = in_array($status_key, ['invalid', 'locked', 'csrf'], true);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paketler - Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --gold: #a6803f;
            --navy: #0a2f2f;
            --navy-deep: #082727;
            --paper: rgba(255, 255, 255, 0.95);
            --line: #e2e8f0;
            --muted: #66758a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: #0f172a;
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(166, 128, 63, 0.14), transparent 24%),
                radial-gradient(circle at top right, rgba(10, 47, 47, 0.06), transparent 20%),
                linear-gradient(180deg, #f7f2e9 0%, #f7fafc 42%, #edf3f8 100%);
        }

        .page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 1rem 1rem 1.25rem;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.9rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.85rem;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #d4b16c, #a6803f);
            color: #fff;
            font-size: 1.08rem;
            font-weight: 900;
            box-shadow: 0 14px 28px rgba(166, 128, 63, 0.2);
        }

        .brand-copy strong {
            display: block;
            color: var(--navy);
            font-size: 1.04rem;
            font-weight: 800;
            line-height: 1.05;
        }

        .brand-copy span {
            display: block;
            margin-top: 0.15rem;
            color: var(--muted);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .action-link {
            min-height: 44px;
            padding: 0.8rem 1rem;
            border-radius: 14px;
            border: 1px solid rgba(210, 220, 232, 0.9);
            background: rgba(255, 255, 255, 0.85);
            color: var(--navy);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .action-link:hover {
            transform: translateY(-2px);
            border-color: rgba(166, 128, 63, 0.45);
            background: #fffdf8;
        }

        .action-link.primary {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, var(--navy), var(--navy-deep));
            box-shadow: 0 14px 24px rgba(10, 47, 47, 0.14);
        }

        .hero {
            padding: 1rem 1.1rem;
            border: 1px solid rgba(166, 128, 63, 0.14);
            border-radius: 28px;
            background:
                radial-gradient(circle at top right, rgba(166, 128, 63, 0.12), transparent 30%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 252, 247, 0.96));
            box-shadow: 0 20px 42px rgba(15, 23, 42, 0.05);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.38rem 0.78rem;
            border-radius: 999px;
            background: rgba(166, 128, 63, 0.12);
            color: #885f23;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin: 0.55rem 0 0;
            color: var(--navy);
            font-size: clamp(1.7rem, 2.8vw, 2.25rem);
            line-height: 1.02;
            letter-spacing: -0.05em;
        }

        .hero p {
            margin: 0.5rem 0 0;
            max-width: 58ch;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .alert {
            margin-top: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.88rem 1rem;
            border-radius: 18px;
            font-size: 0.9rem;
            font-weight: 800;
        }

        .alert.success {
            border: 1px solid rgba(166, 128, 63, 0.22);
            background: linear-gradient(180deg, #fffaf0 0%, #fffdf9 100%);
            color: var(--navy);
            box-shadow: 0 12px 28px rgba(166, 128, 63, 0.08);
        }

        .alert.error {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .package-grid {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 300px));
            gap: 1rem;
            justify-content: center;
            align-items: stretch;
        }

        .package-card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 438px;
            padding: 1.08rem;
            border-radius: 28px;
            border: 1px solid rgba(232, 236, 242, 0.96);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(252, 253, 255, 0.96));
            box-shadow:
                0 18px 36px rgba(15, 23, 42, 0.045),
                0 2px 0 rgba(255, 255, 255, 0.85) inset;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
        }

        .package-card:hover {
            transform: translateY(-4px);
            border-color: rgba(220, 226, 235, 0.98);
            box-shadow:
                0 24px 48px rgba(15, 23, 42, 0.07),
                0 0 0 1px rgba(255, 255, 255, 0.88) inset,
                0 0 24px rgba(255, 244, 220, 0.5);
        }

        .package-card.current {
            border: 1px solid rgba(191, 160, 98, 0.55);
            box-shadow:
                0 26px 54px rgba(166, 128, 63, 0.12),
                0 0 0 1px rgba(247, 236, 208, 0.9) inset,
                0 0 28px rgba(232, 209, 162, 0.32);
        }

        .package-card::after {
            content: '';
            position: absolute;
            inset: 10px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.75);
            pointer-events: none;
            opacity: 0.95;
        }

        .package-card::before {
            content: '';
            display: block;
            height: 4px;
            margin: -1.08rem -1.08rem 0.95rem;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(90deg, rgba(151, 165, 186, 0.48), rgba(225, 206, 167, 0.62));
        }

        .package-card.package-classic {
            background:
                radial-gradient(circle at top right, rgba(201, 210, 222, 0.18), transparent 38%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(250, 251, 253, 0.97));
        }

        .package-card.package-classic::before {
            background: linear-gradient(90deg, rgba(163, 177, 195, 0.9), rgba(217, 225, 233, 0.92));
        }

        .package-card.package-smart {
            background:
                radial-gradient(circle at top right, rgba(234, 217, 183, 0.26), transparent 40%),
                linear-gradient(180deg, rgba(255, 254, 250, 0.99), rgba(255, 255, 255, 0.97));
            border-color: rgba(214, 197, 160, 0.55);
            box-shadow:
                0 22px 48px rgba(166, 128, 63, 0.08),
                0 0 0 1px rgba(249, 239, 217, 0.85) inset,
                0 0 26px rgba(244, 229, 195, 0.3);
        }

        .package-card.package-smart::before {
            background: linear-gradient(90deg, rgba(166, 128, 63, 0.92), rgba(226, 197, 131, 0.95));
        }

        .package-card.package-panel {
            background:
                radial-gradient(circle at top right, rgba(193, 222, 222, 0.22), transparent 38%),
                linear-gradient(180deg, rgba(251, 254, 254, 0.99), rgba(255, 255, 255, 0.97));
        }

        .package-card.package-panel::before {
            background: linear-gradient(90deg, rgba(15, 74, 74, 0.9), rgba(140, 188, 188, 0.92));
        }

        .package-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.8rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .package-head-main {
            min-width: 0;
        }

        .package-kicker {
            display: inline-flex;
            align-items: center;
            padding: 0.34rem 0.64rem;
            border-radius: 999px;
            background: rgba(247, 249, 252, 0.96);
            color: #67778d;
            border: 1px solid rgba(226, 232, 240, 0.9);
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .package-card.package-smart .package-kicker {
            background: rgba(255, 248, 235, 0.96);
            border-color: rgba(224, 202, 155, 0.65);
            color: #885f23;
        }

        .package-card.package-panel .package-kicker {
            background: rgba(243, 250, 250, 0.98);
            border-color: rgba(191, 221, 221, 0.9);
            color: #0f4a4a;
        }

        .package-title {
            margin: 0.5rem 0 0;
            color: var(--navy);
            font-size: 1.16rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
        }

        .package-desc {
            margin: 0.38rem 0 0;
            max-width: 18ch;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.48;
        }

        .package-price-wrap {
            text-align: right;
        }

        .package-price {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.46rem 0.72rem;
            border-radius: 16px;
            border: 1px solid rgba(228, 213, 181, 0.92);
            background: linear-gradient(180deg, rgba(255, 252, 246, 0.98), rgba(255, 248, 238, 0.94));
            color: var(--gold);
            font-size: 1.12rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            white-space: nowrap;
            box-shadow: 0 8px 18px rgba(166, 128, 63, 0.08);
        }

        .package-price-note {
            margin-top: 0.38rem;
            color: #728196;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .selection-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            margin-top: 0.85rem;
            padding: 0.44rem 0.78rem;
            border-radius: 999px;
            border: 1px solid rgba(223, 205, 169, 0.82);
            background: linear-gradient(180deg, rgba(255, 251, 244, 0.98), rgba(251, 244, 231, 0.96));
            color: #885f23;
            font-size: 0.7rem;
            font-weight: 800;
            box-shadow: 0 8px 16px rgba(166, 128, 63, 0.07);
        }

        .package-card.current .selection-pill {
            background: linear-gradient(180deg, rgba(255, 250, 241, 0.98), rgba(249, 239, 218, 0.96));
            color: #7a5417;
        }

        .feature-list {
            list-style: none;
            margin: 0.88rem 0 0;
            padding: 0;
            display: grid;
            gap: 0.48rem;
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            padding: 0.62rem 0.72rem;
            border-radius: 14px;
            border: 1px solid rgba(235, 239, 244, 0.9);
            background: rgba(255, 255, 255, 0.86);
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.35;
            box-shadow: 0 8px 18px rgba(148, 163, 184, 0.06);
        }

        .feature-list li i {
            width: 15px;
            height: 15px;
            margin-top: 1px;
            flex-shrink: 0;
            color: #10b981;
        }

        .feature-list.off {
            margin-top: 0.72rem;
        }

        .feature-list.off li {
            color: #94a3b8;
            background: rgba(250, 251, 252, 0.8);
            box-shadow: none;
        }

        .feature-list.off li i {
            color: #cbd5e1;
        }

        .button-stack {
            margin-top: auto;
            padding-top: 1rem;
        }

        .btn-select {
            width: 100%;
            min-height: 54px;
            border: 1px solid rgba(241, 229, 198, 0.72);
            border-radius: 18px;
            background: linear-gradient(180deg, #0b3434 0%, #114949 100%);
            color: #fff;
            font-size: 0.92rem;
            font-weight: 800;
            letter-spacing: 0.01em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            box-shadow:
                0 16px 26px rgba(10, 47, 47, 0.14),
                0 1px 0 rgba(255, 255, 255, 0.16) inset;
            transition: transform 0.22s ease, box-shadow 0.22s ease, filter 0.22s ease;
        }

        .btn-select:hover:not(:disabled) {
            transform: translateY(-2px);
            filter: brightness(1.02);
            box-shadow:
                0 18px 32px rgba(10, 47, 47, 0.18),
                0 0 20px rgba(205, 184, 140, 0.18);
        }

        .btn-select:disabled {
            cursor: not-allowed;
            color: #94a3b8;
            background: linear-gradient(180deg, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
            box-shadow: none;
        }

        .selection-note {
            margin-top: 0.8rem;
            color: #6b7280;
            font-size: 0.71rem;
            line-height: 1.46;
            text-align: center;
        }

        .next-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        @media (max-width: 1080px) {
            .package-grid {
                grid-template-columns: repeat(2, minmax(0, 300px));
            }
        }

        @media (max-width: 760px) {
            .page {
                padding: 0.85rem;
            }

            .topbar,
            .topbar-actions,
            .next-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .package-grid {
                grid-template-columns: 1fr;
            }

            .package-card {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand">
                <div class="brand-mark">Z</div>
                <div class="brand-copy">
                    <strong>Zerosoft QR</strong>
                    <span>Paket Seçimi</span>
                </div>
            </div>
            <div class="topbar-actions">
                <a href="../index.php" class="action-link">
                    <i data-lucide="corner-up-left" style="width:18px; height:18px;"></i>
                    Anasayfaya Dön
                </a>
                <a href="../processes/logout.php" class="action-link">
                    <i data-lucide="log-out" style="width:18px; height:18px;"></i>
                    Çıkış Yap
                </a>
            </div>
        </header>

        <section class="hero">
            <div class="hero-badge">
                <i data-lucide="sparkles" style="width:14px; height:14px;"></i>
                Bağımsız Paket Seçimi
            </div>
            <h1>Size Uygun Paketi Seçin</h1>
            <p><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>, başvurunuz tamamlandı. Aşağıdan ihtiyacınıza en uygun paketi seçerek devam edebilirsiniz.</p>
        </section>

        <?php if ($status_message !== ''): ?>
            <div class="alert <?php echo $status_is_error ? 'error' : 'success'; ?>">
                <i data-lucide="<?php echo $status_is_error ? 'alert-circle' : 'badge-check'; ?>" style="width:18px; height:18px; color: <?php echo $status_is_error ? '#991b1b' : '#A6803F'; ?>;"></i>
                <?php echo htmlspecialchars($status_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <section class="package-grid">
            <?php foreach ($active_package_definitions as $slug => $definition): ?>
                <?php
                    $slug = (string)$slug;
                    $is_current = $current_package_slug === $slug;
                    $is_pending = $pending_package_slug === $slug;
                    $select_disabled = $is_current || !$can_change_package || $is_pending;
                    $included_features = array_slice((array)($definition['included_features'] ?? []), 0, 3);
                    $excluded_features = array_slice((array)($definition['excluded_features'] ?? []), 0, 1);
                    $package_kicker = $slug === 'smart'
                        ? 'En Çok Tercih Edilen'
                        : ($slug === 'panel' ? 'Dijital Odaklı' : 'Klasik Başlangıç');
                    $package_price_note = $slug === 'smart'
                        ? 'Panel + Baskı'
                        : ($slug === 'panel' ? 'Yıllık Erişim' : 'Baskı Hizmeti');
                ?>
                <article class="package-card package-<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?> <?php echo ($is_current || $is_pending) ? 'current' : ''; ?>">
                    <div class="package-card-head">
                        <div class="package-head-main">
                            <div class="package-kicker"><?php echo htmlspecialchars($package_kicker, ENT_QUOTES, 'UTF-8'); ?></div>
                            <h2 class="package-title"><?php echo htmlspecialchars((string)($definition['label'] ?? 'Paket'), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="package-desc"><?php echo htmlspecialchars((string)($definition['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="package-price-wrap">
                            <div class="package-price"><?php echo htmlspecialchars((string)($definition['register_price_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="package-price-note"><?php echo htmlspecialchars($package_price_note, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>

                    <?php if ($is_current): ?>
                        <div class="selection-pill">
                            <i data-lucide="badge-check" style="width:14px; height:14px;"></i>
                            Aktif paketiniz
                        </div>
                    <?php elseif ($is_pending): ?>
                        <div class="selection-pill">
                            <i data-lucide="wallet" style="width:14px; height:14px;"></i>
                            Satın alma için seçildi
                        </div>
                    <?php endif; ?>

                    <div style="flex:1; margin-top:0.1rem;">
                        <?php if (!empty($included_features)): ?>
                            <ul class="feature-list">
                                <?php foreach ($included_features as $feature): ?>
                                    <li><i data-lucide="check-circle-2"></i> <?php echo htmlspecialchars((string)$feature, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($excluded_features)): ?>
                            <ul class="feature-list off">
                                <?php foreach ($excluded_features as $feature): ?>
                                    <li><i data-lucide="minus-circle"></i> <?php echo htmlspecialchars((string)$feature, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="button-stack">
                        <form method="POST" action="../processes/customer_package_select.php">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="package" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="purchase_mode" value="direct">
                            <button type="submit" class="btn-select" <?php echo $select_disabled ? 'disabled' : ''; ?>>
                                <?php if ($is_current): ?>
                                    <i data-lucide="star"></i> Aktif Paket
                                <?php elseif ($is_pending): ?>
                                    <i data-lucide="check"></i> Satın Alma İçin Seçildi
                                <?php else: ?>
                                    <i data-lucide="arrow-right-circle"></i> Bu Paketi Seç
                                <?php endif; ?>
                            </button>
                        </form>

                        <div class="selection-note">
                            Seçiminiz kaydedilir. Satın alma adımı açıldığında bu paket üzerinden devam edilir.
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($has_active_package): ?>
            <div class="next-actions">
                <a href="new-order.php" class="action-link primary">
                    <i data-lucide="send" style="width:18px; height:18px;"></i>
                    Sipariş Ekranına Geç
                </a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
