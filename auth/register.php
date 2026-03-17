<?php
require_once '../core/db.php';
require_once '../core/security.php';
ensure_session_started();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Başvuru Yap — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --navy-blue: #0A2F2F;
            --navy-dark: #072424;
            --gold: #A6803F;
            --gold-light: #C5A059;
        }

        body {
            background: #f8fafc;
            color: #1e293b;
            font-family: 'Inter', sans-serif;
        }

        .auth-layout {
            display: grid;
            grid-template-columns: 1.2fr 2fr;
            min-height: 100vh;
        }

        .auth-sidebar {
            background: var(--navy-blue);
            color: #fff;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            position: relative;
            overflow: hidden;
        }

        .auth-sidebar-content {
            margin-top: 4rem;
        }

        .auth-sidebar h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 2rem;
            line-height: 1.2;
        }

        .benefit-list {
            list-style: none;
            margin-top: 3rem;
        }

        .benefit-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            opacity: 0.9;
        }

        .benefit-list i {
            color: var(--gold);
        }

        .auth-main {
            padding: 4rem;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            background: #fff;
            overflow-y: auto;
        }

        .form-container {
            width: 100%;
            max-width: 650px;
            padding-top: 2rem;
        }

        /* STEPPER STYLES */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4rem;
            position: relative;
        }

        .stepper::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #f1f5f9;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: #94a3b8;
            transition: all 0.3s;
        }

        .step-item.active .step-dot {
            background: var(--navy-blue);
            border-color: var(--navy-blue);
            color: #fff;
            box-shadow: 0 0 0 6px rgba(10, 47, 47, 0.1);
        }

        .step-item.completed .step-dot {
            background: var(--gold);
            border-color: var(--gold);
            color: #fff;
        }

        .step-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .step-item.active .step-label { color: var(--navy-blue); }

        .form-header {
            margin-bottom: 3rem;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--navy-blue);
        }

        .form-header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .package-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .package-card {
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            text-align: center;
        }

        .package-card input {
            position: absolute;
            opacity: 0;
        }

        .package-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-3px);
        }

        .package-card.active {
            border-color: var(--gold);
            background: rgba(166, 128, 63, 0.02);
            box-shadow: 0 10px 20px rgba(166, 128, 63, 0.05);
        }

        .package-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .package-card .price {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--navy-blue);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1.2rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            color: #1e293b;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(166, 128, 63, 0.1);
        }

        .file-upload-box {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: #f8fafc;
        }

        .file-upload-box:hover {
            border-color: var(--gold);
            background: rgba(166, 128, 63, 0.02);
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .checkbox-group label {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
        }

        .step-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-register-submit, .btn-next {
            flex: 2;
            background: var(--navy-blue);
            color: #fff;
            padding: 1.2rem;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .btn-prev {
            flex: 1;
            background: #fff;
            color: var(--navy-blue);
            padding: 1.2rem;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-register-submit:hover, .btn-next:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            background: var(--navy-dark);
        }

        .btn-prev:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .back-link {
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
            margin-bottom: 2rem;
        }

        .back-link:hover { color: #fff; }

        @media (max-width: 1024px) {
            .auth-layout { grid-template-columns: 1fr; }
            .auth-sidebar { display: none; }
            .auth-main { padding: 3rem 1.5rem; }
        }

        @media (max-width: 600px) {
            .package-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .stepper { margin-bottom: 2rem; }
            .step-label { display: none; }
        }
    </style>
</head>
<body>

    <div class="auth-layout">
        <div class="auth-sidebar">
            <a href="../index.php" class="back-link">
                <i data-lucide="arrow-left" style="width: 16px;"></i> Anasayfaya Dön
            </a>
            <div class="auth-sidebar-content">
                <h2>Yeni Nesil <br>Dijital Dünyaya <br>Hoş Geldiniz</h2>
                <p style="opacity: 0.7; font-size: 1.1rem; margin-top: 1rem;">Binlerce profesyonelin tercihi olan Zerosoft QR sistemiyle tanışın.</p>
                
                <ul class="benefit-list">
                    <li><i data-lucide="check-circle-2"></i> Dinamik Profil Paneli</li>
                    <li><i data-lucide="check-circle-2"></i> Tek Tıkla Rehbere Kaydetme</li>
                    <li><i data-lucide="check-circle-2"></i> Otomatik QR Kod Üretimi</li>
                    <li><i data-lucide="check-circle-2"></i> Profesyonel Kartvizit Baskısı</li>
                </ul>
                <div style="margin-top: 4rem; width: 60px; height: 6px; background: var(--gold); border-radius: 10px;"></div>
            </div>
        </div>

        <main class="auth-main">
            <div class="form-container">
                <!-- PROGRESS STEPPER -->
                <div class="stepper">
                    <div class="step-item active" id="step-id-1">
                        <div class="step-dot">1</div>
                        <div class="step-label">Paket</div>
                    </div>
                    <div class="step-item" id="step-id-2">
                        <div class="step-dot">2</div>
                        <div class="step-label">Hesap</div>
                    </div>
                    <div class="step-item" id="step-id-3">
                        <div class="step-dot">3</div>
                        <div class="step-label">Detaylar</div>
                    </div>
                </div>

                <form id="multi-step-form" action="../processes/register_process.php" method="POST" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    
                    <!-- STEP 1: PACKAGE -->
                    <div class="form-step active" id="step-1">
                        <div class="form-header">
                            <h1>Paketinizi Seçin</h1>
                            <p>İhtiyacınıza en uygun özelliklere sahip paketi belirleyin.</p>
                        </div>

                        <div class="package-grid">
                            <label class="package-card" onclick="selectPackage(this)">
                                <input type="radio" name="package" value="classic">
                                <h4>Klasik</h4>
                                <span class="price">799 ₺</span>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">Sadece Baskı</p>
                            </label>
                            <label class="package-card active" onclick="selectPackage(this)">
                                <input type="radio" name="package" value="smart" checked>
                                <h4>Akıllı</h4>
                                <span class="price">1.299 ₺</span>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">Panel + Baskı</p>
                            </label>
                            <label class="package-card" onclick="selectPackage(this)">
                                <input type="radio" name="package" value="panel">
                                <h4>Sadece Panel</h4>
                                <span class="price">499 ₺/yıl</span>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">Sadece Dijital</p>
                            </label>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn-next" onclick="nextStep(2)">
                                Devam Et <i data-lucide="arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: ACCOUNT -->
                    <div class="form-step" id="step-2">
                        <div class="form-header">
                            <h1>Hesap Bilgileri</h1>
                            <p>Profilinizi yönetmek için gerekli bilgileri girin.</p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Ad Soyad</label>
                                <input type="text" name="name" class="form-control" placeholder="Mehmet Yılmaz">
                            </div>
                            <div class="form-group">
                                <label>E-posta Adresi</label>
                                <input type="email" name="email" class="form-control" placeholder="mehmet@email.com">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Telefon Numarası</label>
                                <input type="tel" name="phone" class="form-control" placeholder="0532 ...">
                            </div>
                            <div class="form-group">
                                <label>Şifre Belirleyin</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                            </div>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn-prev" onclick="prevStep(1)">Geri</button>
                            <button type="button" class="btn-next" onclick="nextStep(3)">
                                Devam Et <i data-lucide="arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: CARD DETAILS -->
                    <div class="form-step" id="step-3">
                        <div class="form-header">
                            <h1>Kartvizit Özellikleri</h1>
                            <p>Tasarım tercihlerinizi ve varsa logonuzu ekleyin.</p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Şirket Adı</label>
                                <input type="text" name="company_name" class="form-control" placeholder="Zerosoft Teknoloji">
                            </div>
                            <div class="form-group">
                                <label>Mesleki Unvan</label>
                                <input type="text" name="job_title" class="form-control" placeholder="Yazılım Geliştirici">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Kurumsal Logo (Varsa)</label>
                            <div class="file-upload-box" onclick="document.getElementById('logo-file').click()">
                                <i data-lucide="upload-cloud" style="width: 32px; height: 32px; color: #94a3b8; margin-bottom: 0.5rem;"></i>
                                <p id="file-name">Logo dosyasını sürükleyin veya seçin</p>
                                <input type="file" name="logo" id="logo-file" hidden onchange="updateFileName(this)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tasarım İstekleriniz (Renk, Stil vb.)</label>
                            <textarea name="design_notes" class="form-control" rows="3" placeholder="Örn: Siyah zemin üzerine altın yaldızlı logomuz olsun..."></textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="kvkk" name="kvkk_approved" value="1" required>
                            <label for="kvkk">
                                <a href="#" style="color: var(--gold); font-weight: 700; text-decoration: none;">KVKK Aydınlatma Metni</a>'ni okudum ve onaylıyorum.
                            </label>
                        </div>

                        <div class="step-actions">
                            <button type="button" class="btn-prev" onclick="prevStep(2)">Geri</button>
                            <button type="submit" class="btn-register-submit">
                                Siparişi Tamamla <i data-lucide="check" style="vertical-align: middle; margin-left: 0.5rem;"></i>
                            </button>
                        </div>
                    </div>

                    <p style="text-align: center; margin-top: 2rem; font-size: 0.95rem; color: #64748b;">
                        Zaten üye misiniz? <a href="login.php" style="color: var(--gold); font-weight: 800; text-decoration: none;">Giriş Yap</a>
                    </p>
                </form>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        let currentStep = 1;

        function nextStep(step) {
            // Basic validation for Step 2
            if (currentStep === 2) {
                const inputs = document.querySelectorAll('#step-2 input');
                let valid = true;
                inputs.forEach(input => {
                    if(!input.value) {
                        input.style.borderColor = 'red';
                        valid = false;
                    } else {
                        input.style.borderColor = '#e2e8f0';
                    }
                });
                if(!valid) return;
            }

            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-id-${currentStep}`).classList.add('completed');
            document.getElementById(`step-id-${currentStep}`).classList.remove('active');
            
            currentStep = step;
            
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step-id-${currentStep}`).classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep(step) {
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            document.getElementById(`step-id-${currentStep}`).classList.remove('active');
            
            currentStep = step;
            
            document.getElementById(`step-${currentStep}`).classList.add('active');
            document.getElementById(`step-id-${currentStep}`).classList.remove('completed');
            document.getElementById(`step-id-${currentStep}`).classList.add('active');
        }

        function selectPackage(card) {
            document.querySelectorAll('.package-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            card.querySelector('input').checked = true;
        }

        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : "Logo dosyasını sürükleyin veya seçin";
            document.getElementById('file-name').innerText = fileName;
        }
    </script>
</body>
</html>
