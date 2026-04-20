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
$active_package_slug = (string)($package_state['package_slug'] ?? '');
$pending_package_slug = (string)($package_state['pending_package_slug'] ?? '');

if ($active_package_slug !== '') {
    header('Location: dashboard.php');
    exit();
}

if ($pending_package_slug !== '') {
    header('Location: purchase-review.php');
    exit();
}

header('Location: packages.php');
exit();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karşılama - Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --navy: #0A2F2F;
            --navy-dark: #072424;
            --gold: #A6803F;
            --paper: #f6f1e8;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(166, 128, 63, 0.12), transparent 24%),
                linear-gradient(180deg, #f4efe5 0%, #f8fafc 48%, #edf2f7 100%);
            color: #0f172a;
        }

        .setup-layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 320px 1fr;
        }

        .setup-sidebar {
            padding: 2rem 1.4rem;
            color: #fff;
            background:
                radial-gradient(circle at top, rgba(197, 160, 89, 0.2), transparent 20%),
                linear-gradient(180deg, #082626 0%, #0A2F2F 56%, #0d3939 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        .setup-brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .setup-logo {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #d6af64, #a6803f);
            color: #082626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.35rem;
        }

        .setup-steps {
            margin: 2.2rem 0 0;
            display: grid;
            gap: 0.8rem;
        }

        .setup-step {
            border-radius: 18px;
            padding: 1rem 1.05rem;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
        }

        .setup-step.active {
            background: linear-gradient(135deg, rgba(214, 175, 100, 0.96), rgba(166, 128, 63, 0.94));
            color: #082626;
            box-shadow: 0 16px 28px rgba(0,0,0,0.18);
        }

        .setup-step-label {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
            opacity: 0.82;
        }

        .setup-step-title {
            margin-top: 0.35rem;
            font-size: 1rem;
            font-weight: 800;
        }

        .setup-step-copy {
            margin-top: 0.35rem;
            font-size: 0.84rem;
            line-height: 1.55;
            opacity: 0.82;
        }

        .setup-note {
            margin-top: 2rem;
            padding: 1rem;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
            line-height: 1.7;
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
        }

        .setup-main {
            padding: 2rem;
            display: flex;
            align-items: center;
        }

        .hero-card {
            width: 100%;
            max-width: 1120px;
            margin: 0 auto;
            border-radius: 32px;
            padding: 2rem;
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(255,255,255,0.78);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(18px);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.95fr);
            gap: 1.2rem;
            align-items: stretch;
        }

        .hero-panel,
        .summary-panel {
            border-radius: 26px;
            padding: 1.5rem;
        }

        .hero-panel {
            background:
                radial-gradient(circle at top left, rgba(166, 128, 63, 0.14), transparent 24%),
                linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
            border: 1px solid #e2e8f0;
        }

        .summary-panel {
            background: linear-gradient(180deg, #0A2F2F 0%, #103c3c 100%);
            color: #fff;
            border: 1px solid rgba(10, 47, 47, 0.18);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            background: rgba(166, 128, 63, 0.12);
            color: #8a6428;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        h1 {
            margin: 1rem 0 0;
            color: var(--navy);
            font-size: clamp(2rem, 3vw, 3rem);
            line-height: 1.08;
            letter-spacing: -0.04em;
        }

        .lead {
            margin: 1rem 0 0;
            color: #475569;
            font-size: 1.03rem;
            line-height: 1.85;
            max-width: 44rem;
        }

        .insight-grid {
            margin-top: 1.25rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .insight-card {
            border-radius: 20px;
            padding: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .insight-card strong {
            display: block;
            color: var(--navy);
            font-weight: 800;
        }

        .insight-card span {
            display: block;
            margin-top: 0.35rem;
            color: #64748b;
            line-height: 1.6;
            font-size: 0.86rem;
        }

        .summary-panel h2 {
            margin: 0;
            font-size: 1.15rem;
        }

        .summary-list {
            margin: 1rem 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.9rem;
        }

        .summary-item {
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 0.8rem;
            align-items: start;
        }

        .summary-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .summary-item strong {
            display: block;
            font-size: 0.95rem;
        }

        .summary-item p {
            margin: 0.28rem 0 0;
            color: rgba(255,255,255,0.74);
            line-height: 1.6;
            font-size: 0.84rem;
        }

        .cta-row {
            margin-top: 1.4rem;
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .btn-primary,
        .btn-secondary {
            min-height: 48px;
            border-radius: 14px;
            padding: 0.9rem 1.15rem;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }

        .btn-primary {
            color: #fff;
            border: 1px solid transparent;
            background:
                linear-gradient(135deg, #082a2a, #114444) padding-box,
                linear-gradient(135deg, rgba(247, 233, 191, 1), rgba(166, 128, 63, 1)) border-box;
            box-shadow: 0 16px 32px rgba(8, 42, 42, 0.16);
        }

        .btn-secondary {
            color: var(--navy);
            background: #fff;
            border: 1px solid #d7dee7;
        }

        @media (max-width: 1080px) {
            .setup-layout {
                grid-template-columns: 1fr;
            }

            .setup-sidebar {
                display: none;
            }

            .setup-main {
                padding: 1rem;
            }

            .hero-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .hero-card {
                padding: 1rem;
                border-radius: 24px;
            }

            .insight-grid {
                grid-template-columns: 1fr;
            }

            .hero-panel,
            .summary-panel {
                padding: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="setup-layout">
        <aside class="setup-sidebar">
            <div class="setup-brand">
                <div class="setup-logo">Z</div>
                <div>Zerosoft Kurulum</div>
            </div>

            <div class="setup-steps">
                <section class="setup-step active">
                    <div class="setup-step-label">Adım 1</div>
                    <div class="setup-step-title">Karşılama</div>
                    <div class="setup-step-copy">Önce işleyişi görün, sonra karar verin.</div>
                </section>
                <section class="setup-step">
                    <div class="setup-step-label">Adım 2</div>
                    <div class="setup-step-title">Paket Seçimi</div>
                    <div class="setup-step-copy">İhtiyacınıza göre en doğru paketi karşılaştırın.</div>
                </section>
                <section class="setup-step">
                    <div class="setup-step-label">Adım 3</div>
                    <div class="setup-step-title">Satın Alma Hazırlığı</div>
                    <div class="setup-step-copy">Şimdilik ödeme yok; seçim mantığını birlikte netleştiriyoruz.</div>
                </section>
            </div>

            <div class="setup-note">
                Bu kurulum akışı, kullanıcıyı doğrudan fiyat baskısına sokmadan önce ürünü anlatır. Premium hissi yalnızca görsel kaliteyle değil, doğru sırayla akan deneyimle de oluşur.
            </div>
        </aside>

        <main class="setup-main">
            <section class="hero-card">
                <div class="hero-grid">
                    <div class="hero-panel">
                        <span class="eyebrow">
                            <i data-lucide="sparkles" style="width:14px; height:14px;"></i>
                            Hoş Geldiniz
                        </span>
                        <h1>Merhaba <?php echo htmlspecialchars($user_name); ?>, şimdi Zerosoft işleyişini kısa ve net şekilde görün</h1>
                        <p class="lead">
                            Bu panelde sizden hemen paket satın almanız beklenmez. Önce sistemin nasıl çalıştığını, hangi adımda ne göreceğinizi ve hangi paketin hangi ihtiyaca karşılık geldiğini şeffaf biçimde görürsünüz. Ardından size uygun paketi seçip satın alma hazırlık ekranına geçersiniz.
                        </p>

                        <div class="insight-grid">
                            <article class="insight-card">
                                <strong>Önce güven</strong>
                                <span>Hesabınızı açtınız, şimdi ürünün yapısını ve hizmet standardını tanıyorsunuz.</span>
                            </article>
                            <article class="insight-card">
                                <strong>Sonra paket</strong>
                                <span>Paketler, avantajları ve farklarıyla birlikte ayrı ekranda sunuluyor.</span>
                            </article>
                            <article class="insight-card">
                                <strong>Sonra satın alma</strong>
                                <span>Seçiminiz kayda alınır; ödeme modülü açıldığında aynı akış üzerinden devam eder.</span>
                            </article>
                        </div>

                        <div class="cta-row">
                            <a href="packages.php" class="btn-primary">
                                Paketleri İncele
                                <i data-lucide="arrow-right" style="width:18px; height:18px;"></i>
                            </a>
                            <a href="../processes/logout.php" class="btn-secondary">Çıkış Yap</a>
                        </div>
                    </div>

                    <aside class="summary-panel">
                        <h2>Bu akışta sizi ne bekliyor?</h2>
                        <ol class="summary-list">
                            <li class="summary-item">
                                <div class="summary-icon"><i data-lucide="layout-template" style="width:20px; height:20px;"></i></div>
                                <div>
                                    <strong>Akış netleşir</strong>
                                    <p>Önce süreç anlatılır, ardından paket seçimine geçilir. Kullanıcı neyle karşılaşacağını bilir.</p>
                                </div>
                            </li>
                            <li class="summary-item">
                                <div class="summary-icon"><i data-lucide="layers-3" style="width:20px; height:20px;"></i></div>
                                <div>
                                    <strong>Paket farkları görünür</strong>
                                    <p>Dijital profil, baskı, QR paylaşımı ve revize hakları ayrı ayrı değerlendirilir.</p>
                                </div>
                            </li>
                            <li class="summary-item">
                                <div class="summary-icon"><i data-lucide="wallet" style="width:20px; height:20px;"></i></div>
                                <div>
                                    <strong>Satın alma hazırlığı</strong>
                                    <p>Şimdilik ödeme adımı kapalı. Bu yüzden seçim mantığı güvenli ve test edilebilir şekilde ilerler.</p>
                                </div>
                            </li>
                        </ol>
                    </aside>
                </div>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
