<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Kartvizit — Dijital Kartvizit Paneli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <!-- HEADER / NAVIGATION -->
    <nav class="main-nav">
        <div class="container nav-flex">
            <a href="/" class="brand">
                <div class="brand-logotype">
                    <img src="assets/img/logo.png" alt="QR Kartvizit">
                    <span>QR Kartvizit</span>
                </div>
            </a>
            <div class="nav-links">
                <a href="#anasayfa">Ana Sayfa</a>
                <a href="#ozellikler">Özellikler</a>
                <a href="#fiyatlandirma">Fiyatlandırma</a>
                <a href="#iletisim">İletişim</a>
            </div>
            <div class="nav-btns">
                <button class="mobile-menu-btn" aria-label="Menü">
                    <i data-lucide="menu"></i>
                </button>
                <button class="theme-switch"><i data-lucide="sun"></i></button>
                <a href="auth/login.php" class="btn-outline-sm">Giriş Yap</a>
                <a href="auth/register.php" class="btn-dark-sm">Başvuru Yap</a>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section" id="anasayfa">
        <div class="container grid-2">
            <div class="hero-text-block">
                <div class="badge-premium" style="color: var(--gold); border-color: rgba(166, 128, 63, 0.2);"><i data-lucide="award"></i> Türkiye'nin Dijital Kartvizit Platformu</div>
                <h1>Kartvizitiniz Artık <br><span class="highlight-yellow">Dijital</span> ve <span class="highlight-yellow">Akıllı</span></h1>
                <p>Basılı kartvizit + QR kod + dijital profil. Tek platformda hepsi bir arada. Müşterileriniz kartı taradığında tüm bilgilerinize anında ulaşsın.</p>
                <div class="hero-actions">
                    <a href="auth/register.php" class="btn-white-hero">
                        Hemen Başvur <i data-lucide="chevron-right"></i>
                    </a>
                    <a href="#ornekler" class="btn-ghost-hero">Örnekleri Gör</a>
                </div>
                <div class="hero-trust-badges">
                    <span><i data-lucide="star"></i> 4.9/5 Puan</span>
                    <span><i data-lucide="truck"></i> Ücretsiz Kargo</span>
                    <span><i data-lucide="shield-check"></i> Güvenli Ödeme</span>
                </div>
            </div>
            <div class="hero-visual-block">
                <div class="visual-stack">
                    <!-- Dark Card (Background Left) -->
                    <div class="v-card-dark">
                        <div class="v-card-logo">
                            <img src="assets/img/logo.png" alt="Logo" style="width:100%; height:100%; object-fit:contain; border-radius:12px;">
                        </div>
                        <div class="v-card-name">ELITE CORP.</div>
                        <div class="v-card-title">Dijital Kartvizit</div>
                        <div class="v-card-info">
                            <span><i data-lucide="cpu" style="width:12px;height:12px;"></i> NFC Teknolojisi</span>
                            <span><i data-lucide="zap" style="width:12px;height:12px;"></i> Anında Paylaşım</span>
                        </div>
                    </div>
                    
                    <!-- Minimalist Premium Phone Screen (Center) -->
                    <div class="v-phone">
                        <div class="v-phone-notch"></div>
                        <div class="v-phone-screen">
                            
                            <!-- VIEW 1: Ultra-Clean Minimal Profile -->
                            <div class="v-profile-view active">
                                <div class="app-statusbar">
                                    <span>9:41</span>
                                    <div class="app-statusbar-icons">
                                        <i data-lucide="signal"></i>
                                        <i data-lucide="wifi"></i>
                                        <i data-lucide="battery-full"></i>
                                    </div>
                                </div>
                                <div class="app-profile-card">
                                    <div class="app-avatar-wrap">
                                        <div class="app-avatar"><i data-lucide="user"></i></div>
                                        <div class="app-verified"><i data-lucide="check-circle-2"></i></div>
                                    </div>
                                    <h4>Mehmet Yılmaz</h4>
                                    <p>CEO & Kurucu Ortak</p>
                                </div>
                                <div class="app-links-minimal">
                                    <div class="app-link-m"><div class="icon-bg-m"><i data-lucide="phone"></i></div><span>Ara</span></div>
                                    <div class="app-link-m"><div class="icon-bg-m"><i data-lucide="mail"></i></div><span>E-posta</span></div>
                                    <div class="app-link-m"><div class="icon-bg-m"><i data-lucide="map-pin"></i></div><span>Konum</span></div>
                                    <div class="app-link-m"><div class="icon-bg-m"><i data-lucide="globe"></i></div><span>Site</span></div>
                                </div>
                                <div class="app-save-btn">
                                    Rehbere Kaydet
                                </div>
                            </div>

                            <!-- VIEW 2: Physical Card Preview -->
                            <div class="v-card-view">
                                <div class="app-statusbar">
                                    <span>9:41</span>
                                    <div class="app-statusbar-icons">
                                        <i data-lucide="signal"></i>
                                        <i data-lucide="wifi"></i>
                                        <i data-lucide="battery-full"></i>
                                    </div>
                                </div>
                                <div class="card-screen-label">Fiziksel Kartvizit</div>
                                <div class="v-mock-card">
                                    <div class="v-card-logo">
                                        <img src="assets/img/logo.png" alt="Logo" style="width:100%; height:100%; object-fit:contain; border-radius:8px;">
                                    </div>
                                    <div class="v-card-name">MEHMET YILMAZ</div>
                                    <div class="v-card-title">CEO / KURUCU</div>
                                    <div class="v-card-qr-small">
                                        <i data-lucide="qr-code"></i>
                                    </div>
                                </div>
                                <div class="card-screen-hint"><i data-lucide="nfc"></i> Ekranı yaklaştırın.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Light Card (Background Right) -->
                    <div class="v-card-light">
                        <div class="v-card-accent"></div>
                        <div class="v-card-name-l">Akıllı Kart</div>
                        <div class="v-card-title-l">Sınırsız Kullanım</div>
                        <div class="v-card-info-l">
                            QR kod ve yakından temas ile anında tüm bilgilerinizi aktarın. Uygulama gerektirmez.
                        </div>
                        <div class="v-card-qr-l"><i data-lucide="qr-code"></i></div>
                    </div>
                </div>
            </div><!-- End hero-visual-block -->
        </div><!-- End container grid-2 -->

        <!-- Hero Wave - Corner-to-Corner Soft Transition -->
        <div class="hero-wave">
            <svg viewBox="0 0 1440 100" preserveAspectRatio="none">
                <path class="wave-layer-1" d="M0,100 L600,100 C900,100 1200,95 1440,70 L1440,100 L0,100 Z"></path>
            </svg>
        </div>
    </section>

    <!-- STATS -->
    <section class="stats-section">
        <div class="container stats-flex">
            <div class="stat-item">
                <i data-lucide="users"></i>
                <div class="stat-num">1.500+</div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
            <div class="stat-item">
                <i data-lucide="heart"></i>
                <div class="stat-num">10.000+</div>
                <div class="stat-label">Mutlu Müşteri</div>
            </div>
            <div class="stat-item">
                <i data-lucide="credit-card"></i>
                <div class="stat-num">50.000+</div>
                <div class="stat-label">Basılan Kartvizit</div>
            </div>
            <div class="stat-item">
                <i data-lucide="star"></i>
                <div class="stat-num">4.9/5</div>
                <div class="stat-label">Müşteri Puanı</div>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="how-it-works-section" id="ozellikler">
        <div class="container">
            <h2 class="section-title">Nasıl Çalışır?</h2>
            <p class="section-desc">5 basit adımda dijital kartvizitiniz hazır</p>
            
            <div class="steps-grid-line">
                <div class="progress-track">
                    <div class="progress-fill"></div>
                </div>
                <!-- Step 1 -->
                <div class="step-card active" data-index="0">
                    <div class="step-badge">1</div>
                    <h4>Başvuru & Ödeme</h4>
                    <p>Bilgilerinizi girin, paketinizi seçin ve güvenle ödeyin.</p>
                </div>
                <!-- Step 2 -->
                <div class="step-card" data-index="1">
                    <div class="step-badge">2</div>
                    <h4>Dijital Paneliniz Aktif</h4>
                    <p>Ödeme sonrası dijital profiliniz ve paneliniz anında aktifleşir.</p>
                </div>
                <!-- Step 3 -->
                <div class="step-card" data-index="2">
                    <div class="step-badge">3</div>
                    <h4>Tasarım İstekleriniz</h4>
                    <p>Renk, stil ve tercihlerinizi form ile ekibimize iletin.</p>
                </div>
                <!-- Step 4 -->
                <div class="step-card" data-index="3">
                    <div class="step-badge">4</div>
                    <h4>Tasarım & Onay</h4>
                    <p>Tasarım ekibimiz kartınızı hazırlar, onayınıza sunar.</p>
                </div>
                <!-- Step 5 -->
                <div class="step-card" data-index="4">
                    <div class="step-badge">5</div>
                    <h4>Basım & Kargo</h4>
                    <p>Onay sonrası kartlarınız basılıp adresinize kargolanır.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- EXAMPLES SECTION -->
    <section class="examples-section" id="ornekler">
        <div class="container">
            <h2 class="section-title">Kartvizit Örnekleri</h2>
            <p class="section-desc">Profesyonel tasarım ekibimiz tarafından oluşturulan örnek kartvizitler</p>
            <div class="examples-row">
                <!-- Example 1 -->
                <div class="ex-card ex-dark">
                    <div class="ex-badge">QR</div>
                    <div class="ex-main">
                        <h3>Mehmet Yılmaz</h3>
                        <p>CEO & Kurucu</p>
                    </div>
                    <div class="ex-info">
                        <span><i data-lucide="phone"></i> 0532 123 45 67</span>
                        <span><i data-lucide="mail"></i> mehmet@firma.com</span>
                    </div>
                    <div class="ex-qr"></div>
                </div>
                <!-- Example 2 -->
                <div class="ex-card ex-light">
                    <div class="ex-badge l">AK</div>
                    <div class="ex-main">
                        <h3>Ayşe Kara</h3>
                        <p>Mimar</p>
                    </div>
                    <div class="ex-info">
                        <span><i data-lucide="phone"></i> 0555 987 65 43</span>
                        <span><i data-lucide="mail"></i> ayse-mimarlik.com</span>
                    </div>
                    <div class="ex-qr"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS SLIDER -->
    <section class="testimonials-section" id="referanslar">
        <div class="container">
            <h2 class="section-title">Başarı Hikayeleri</h2>
            <p class="section-desc">Zerosoft ile dijital dönüşümünü tamamlayan mutlu kullanıcılarımızın görüşleri</p>
            
            <div class="testimonial-slider-wrapper">
                <div class="testimonial-slider">
                    <!-- Testimonial 1 -->
                    <div class="testimonial-item active">
                        <div class="testimonial-card">
                            <div class="quote-icon"><i data-lucide="quote"></i></div>
                            <div class="testimonial-content">
                                <p>"QR Kartvizit hayatımı kolaylaştırdı. Artık yanımda deste deste kartvizit taşımama gerek kalmıyor. Tek tıkla tüm bilgilerimi paylaşabiliyorum ve müşterilerim bu modern yaklaşımdan çok etkileniyor."</p>
                            </div>
                            <div class="testimonial-user">
                                <div class="user-img">
                                    <img src="assets/img/t1.png" alt="Zeynep Kaya">
                                </div>
                                <div class="user-info">
                                    <h5>Zeynep Kaya</h5>
                                    <span>İç Mimarlık Ofisi Sahibi</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="testimonial-item">
                        <div class="testimonial-card">
                            <div class="quote-icon"><i data-lucide="quote"></i></div>
                            <div class="testimonial-content">
                                <p>"İş dünyasında hız her şeydir. Zerosoft'un dijital kartvizit çözümü ile iletişim bilgilerimi gün saniyeler içinde paylaşıyorum. Panel üzerinden bilgilerimi istediğim zaman güncelleyebilmek büyük bir avantaj."</p>
                            </div>
                            <div class="testimonial-user">
                                <div class="user-img">
                                    <img src="assets/img/t2.png" alt="Murat Şahin">
                                </div>
                                <div class="user-info">
                                    <h5>Murat Şahin</h5>
                                    <span>Gayrimenkul Yatırım Danışmanı</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="testimonial-item">
                        <div class="testimonial-card">
                            <div class="quote-icon"><i data-lucide="quote"></i></div>
                            <div class="testimonial-content">
                                <p>"Tasarım ekibinin ilgisi ve hızı gerçekten hayranlık verici. Kartvizitim hem fiziksel hem de dijital olarak harika görünüyor. QR kodun web profilime bu kadar şık entegre edilmesi projeme prestij kattı."</p>
                            </div>
                            <div class="testimonial-user">
                                <div class="user-img">
                                    <img src="assets/img/t3.png" alt="Caner Demir">
                                </div>
                                <div class="user-info">
                                    <h5>Caner Demir</h5>
                                    <span>Grafik Tasarımcı & Kreatif Direktör</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="slider-dots">
                    <span class="dot active" data-index="0"></span>
                    <span class="dot" data-index="1"></span>
                    <span class="dot" data-index="2"></span>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER CTA -->
    <section class="footer-cta">
        <div class="container container-box">
            <h2>Dijital Kartvizitinizi <span class="cyan">Hemen Oluşturun</span></h2>
            <p>Onbinlerce profesyonelin tercihi olan Zerosoft QR sistemiyle tanışın.</p>
            <a href="auth/register.php" class="btn-main">
                Şimdi Başvur <i data-lucide="arrow-right"></i>
            </a>
        </div>
    </section>

    <!-- FOOTER NAV -->
    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="f-brand">
                <div class="brand"><span>QR Kartvizit</span></div>
                <p>Profesyonel kartvizit basımı ve dijital profil yönetimi. İşletmenizi bir QR kod ile dijital dünyaya taşıyın.</p>
            </div>
            <div class="f-nav">
                <h5>ÜRÜN</h5>
                <ul><li>Özellikler</li><li>Fiyatlandırma</li><li>Başvuru Yap</li></ul>
            </div>
            <div class="f-nav">
                <h5>DESTEK</h5>
                <ul><li>İletişim</li><li>Hakkımızda</li></ul>
            </div>
            <div class="f-nav">
                <h5>HESAP</h5>
                <ul><li>Giriş Yap</li><li>Kayıt Ol</li></ul>
            </div>
        </div>
        <div class="container f-bot">
            <p>&copy; 2026 QR Kartvizit. Tüm hakları saklıdır.</p>
            <p>Zerosoft tarafından geliştirilmiştir.</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
