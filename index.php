<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zerosoft QR Kartvizit — Dijital & Basılı Kartvizit Platformu</title>
    <meta name="description" content="Profesyonel dijital kartvizit, QR kod ve baskı hizmeti. Tek platformda fiziksel ve dijital kartvizit çözümleri. Hemen başvurun.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

    <!-- ═══════════════════════════════════════════
         NAVBAR
    ═══════════════════════════════════════════ -->
    <nav class="main-nav" id="navbar">
        <div class="container nav-flex">
            <a href="/" class="brand">
                <div class="brand-logotype">
                    <img src="assets/img/logo.png" alt="QR Kartvizit Logo">
                    <span>QR Kartvizit</span>
                </div>
            </a>
            <div class="nav-links" id="nav-links">
                <a href="#anasayfa">Ana Sayfa</a>
                <a href="#ozellikler">Özellikler</a>
                <a href="#nasil-calisir">Nasıl Çalışır?</a>
                <a href="#fiyatlandirma">Fiyatlandırma</a>
                <a href="#iletisim">İletişim</a>
            </div>
            <div class="nav-btns">
                <button class="mobile-menu-btn" id="menuBtn" aria-label="Menü" aria-expanded="false">
                    <i data-lucide="menu"></i>
                </button>
                <a href="auth/login.php" class="btn-outline-sm">Giriş Yap</a>
                <a href="auth/register.php" class="btn-dark-sm">Başvuru Yap</a>
            </div>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════
         HERO SECTION
    ═══════════════════════════════════════════ -->
    <section class="hero-section" id="anasayfa">
        <div class="hero-bg-glow"></div>
        <div class="container hero-inner">
            <!-- Sol: Metin -->
            <div class="hero-text-block">
                <div class="badge-premium">
                    <i data-lucide="award"></i>
                    Türkiye'nin Dijital Kartvizit Platformu
                </div>
                <h1>Kartvizitiniz Artık<br><span class="highlight-gold">Dijital</span> ve <span class="highlight-gold">Akıllı</span></h1>
                <p>Basılı kartvizit + QR kod + dijital profil. Tek platformda hepsi bir arada. Müşterileriniz kartı taradığında tüm bilgilerinize anında ulaşsın.</p>
                <div class="hero-actions">
                    <a href="auth/register.php" class="btn-cta-primary" id="hero-cta-btn">
                        Hemen Başvur <i data-lucide="arrow-right"></i>
                    </a>
                    <a href="#nasil-calisir" class="btn-cta-ghost">
                        <i data-lucide="play-circle"></i> Nasıl Çalışır?
                    </a>
                </div>
                <div class="hero-trust-badges">
                    <span><i data-lucide="star"></i> 4.9/5 Müşteri Puanı</span>
                    <span><i data-lucide="truck"></i> Ücretsiz Kargo</span>
                    <span><i data-lucide="shield-check"></i> Güvenli Ödeme</span>
                </div>
            </div>

            <!-- Sağ: Telefon Mockup -->
            <div class="hero-visual-block">
                <div class="hero-phone-wrap">


                    <!-- Telefon -->
                    <div class="v-phone" id="v-phone">
                        <div class="v-phone-island"></div>
                        <div class="v-phone-screen">
                            <!-- Ekran 1: Profil Görünümü -->
                            <div class="v-profile-view active" id="profileView">
                                <div class="app-statusbar">
                                    <span>9:41</span>
                                    <div class="app-statusbar-icons">
                                        <i data-lucide="signal"></i>
                                        <i data-lucide="wifi"></i>
                                        <i data-lucide="battery-full"></i>
                                    </div>
                                </div>
                                
                                <div class="v-profile-card-inner">
                                    <!-- Avatar -->
                                    <div class="v-avatar-wrapper">
                                        <div class="v-avatar-ring"></div>
                                        <div class="v-avatar-inner">
                                            <img src="assets/img/t2.png" alt="Mehmet Yılmaz">
                                        </div>
                                    </div>

                                    <div class="app-profile-info">
                                        <h4 class="premium-text-glow">Mehmet Yılmaz</h4>
                                        <p class="premium-subtitle">CEO · Zerosoft</p>
                                    </div>

                                    <!-- Actions Bar -->
                                    <div class="v-actions-bar">
                                        <div class="v-btn-icon"><i data-lucide="qr-code"></i></div>
                                        <div class="v-btn-main">
                                            <i data-lucide="user-plus"></i>
                                            <span>Rehbere Ekle</span>
                                        </div>
                                        <div class="v-btn-icon"><i data-lucide="share-2"></i></div>
                                    </div>

                                    <!-- Contact Grid -->
                                    <div class="app-contact-grid-premium">
                                        <div class="app-contact-tile premium-hover">
                                            <div class="tile-icon-wrap"><i data-lucide="phone"></i></div>
                                            <strong>Ara</strong>
                                        </div>
                                        <div class="app-contact-tile premium-hover">
                                            <div class="tile-icon-wrap"><i data-lucide="mail"></i></div>
                                            <strong>E-posta</strong>
                                        </div>
                                        <div class="app-contact-tile premium-hover">
                                            <div class="tile-icon-wrap"><i data-lucide="map-pin"></i></div>
                                            <strong>Konum</strong>
                                        </div>
                                        <div class="app-contact-tile premium-hover">
                                            <div class="tile-icon-wrap"><i data-lucide="globe"></i></div>
                                            <strong>Web</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Ekran 2: Fiziksel Kart -->
                            <div class="v-card-view" id="cardView">
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
                                    <div class="vmc-top">
                                        <div class="vmc-logo-img">
                                            <img src="assets/img/logo.png" alt="Logo">
                                        </div>
                                        <span class="vmc-nfc">NFC</span>
                                    </div>
                                    <div>
                                        <div class="vmc-name">MEHMET YILMAZ</div>
                                        <div class="vmc-role">CEO / KURUCU ORTAK</div>
                                    </div>
                                    <div class="vmc-bottom">
                                        <div class="vmc-contact">
                                            <span><i data-lucide="phone"></i> 0532 123 45 67</span>
                                            <span><i data-lucide="globe"></i> zerosoft.com.tr</span>
                                        </div>
                                        <div class="vmc-qr"><i data-lucide="qr-code"></i></div>
                                    </div>
                                </div>
                                <div class="card-screen-hint">
                                    <i data-lucide="nfc"></i> Kartı okutun, profili açın
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Floating badge -->
                    <div class="hero-float-badge premium-glow-badge">
                        <i data-lucide="zap"></i>
                        <span>Anında <strong>Aktif</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dalga geçişi - ince profesyonel -->
        <div class="hero-wave" style="line-height: 0; font-size: 0; transform: translateY(1.5px); margin-top: -1px;">
            <svg viewBox="0 0 1440 28" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" style="display: block; width: 100%; height: 32px;">
                <path fill="#ffffff" d="M0,28 C240,16 480,6 720,12 C960,18 1200,10 1440,4 L1440,28 Z"/>
            </svg>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         STATS SECTION
    ═══════════════════════════════════════════ -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrap" style="background: rgba(10,47,47,0.08);">
                        <i data-lucide="users" style="color: var(--navy-blue);"></i>
                    </div>
                    <div class="stat-num">1.500+</div>
                    <div class="stat-label">Aktif Kullanıcı</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap" style="background: rgba(166,128,63,0.1);">
                        <i data-lucide="heart" style="color: var(--gold);"></i>
                    </div>
                    <div class="stat-num">10.000+</div>
                    <div class="stat-label">Mutlu Müşteri</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap" style="background: rgba(10,47,47,0.08);">
                        <i data-lucide="credit-card" style="color: var(--navy-blue);"></i>
                    </div>
                    <div class="stat-num">50.000+</div>
                    <div class="stat-label">Basılan Kartvizit</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap" style="background: rgba(166,128,63,0.1);">
                        <i data-lucide="star" style="color: var(--gold);"></i>
                    </div>
                    <div class="stat-num">4.9/5</div>
                    <div class="stat-label">Müşteri Puanı</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         FEATURES SECTION
     ═══════════════════════════════════════════ -->
    <section class="features-section" id="ozellikler">
        <div class="container">
            <div class="section-label">ÖZELLİKLER</div>
            <h2 class="section-title">Neden <span class="gold-text">Zerosoft</span>?</h2>
            <p class="section-desc">Tek platformda fiziksel ve dijital dünyayı birleştiren akıllı kartvizit çözümü</p>

            <div class="features-elegant-grid">
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="qr-code"></i></div>
                    <h3>Dinamik QR Kod</h3>
                    <p>Kartınız basıldıktan sonra bile içeriğini istediğiniz zaman güncelleyin. Her zaman güncel kalın.</p>
                </div>
                
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="smartphone"></i></div>
                    <h3>Dijital Profil</h3>
                    <p>Sosyal medya, telefon, e-posta iletişim kanallarınızın tamamı tek bir akıllı sayfada toplanır.</p>
                </div>
                
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="printer"></i></div>
                    <h3>Profesyonel Baskı</h3>
                    <p>Tasarım ekibimiz kartınızı hazırlar, yüksek kaliteli baskı ile kapınıza ücretsiz kargo ile gönderir.</p>
                </div>
                
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="edit-3"></i></div>
                    <h3>Kolay Düzenleme</h3>
                    <p>Kullanıcı dostu panelimizden bilgilerinizi anında güncelleyin. Tasarım bilgisi gerektirmez.</p>
                </div>
                
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="share-2"></i></div>
                    <h3>Sınırsız Paylaşım</h3>
                    <p>QR kodu ile veya size özel linkinizle profilinizi WhatsApp, SMS veya e-posta üzerinden paylaşın.</p>
                </div>
                
                <div class="elegant-feature">
                    <div class="ef-icon-ring"><i data-lucide="bar-chart-2"></i></div>
                    <h3>Detaylı İstatistikler</h3>
                    <p>Ziyaretçi verilerinizi anlık olarak takip edin. Profilinizin kaç kez görüntülendiğini analiz edin.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         HOW IT WORKS
    ═══════════════════════════════════════════ -->
    <section class="how-it-works-section" id="nasil-calisir">
        <div class="container">
            <div class="section-label">SÜREÇ</div>
            <h2 class="section-title">Nasıl Çalışır?</h2>
            <p class="section-desc">Geleneksel kartvizitleri dijitalin gücüyle birleştiren 4 basit adım</p>

            <div class="steps-grid-line">
                <div class="progress-track">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="step-card active" data-index="0">
                    <div class="step-badge">1</div>
                    <h4>Hızlı Başvuru</h4>
                    <p>Ad soyad ve temel bilgilerinizle saniyeler içinde kaydınızı tamamlayın.</p>
                </div>
                <div class="step-card" data-index="1">
                    <div class="step-badge">2</div>
                    <h4>Paketinizi Seçin</h4>
                    <p>Size en uygun paketi seçin; dijital profiliniz ve paneliniz anında aktifleşsin.</p>
                </div>
                <div class="step-card" data-index="2">
                    <div class="step-badge">3</div>
                    <h4>Profilini Tasarla</h4>
                    <p>Panelden sosyal medya, iletişim ve firma bilgilerini girerek kartını kişiselleştir.</p>
                </div>
                <div class="step-card" data-index="3">
                    <div class="step-badge">4</div>
                    <h4>Baskı & Teslimat</h4>
                    <p>Dilerseniz fiziksel kart siparişi verin; tasarım ekibimiz hazırlayıp adresinize göndersin.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         EXAMPLES / SHOWCASE
    ═══════════════════════════════════════════ -->
    <section class="examples-section" id="ornekler">
        <div class="container">
            <div class="section-label">ÖRNEKLER</div>
            <h2 class="section-title">Kartvizit Örnekleri</h2>
            <p class="section-desc">Profesyonel tasarım ekibimiz tarafından hazırlanan premium kartvizit tasarımları</p>
            <div class="examples-grid">
                
                <!-- Koyu Kartvizit -->
                <div class="ex-item">
                    <div class="ex-card-wrapper ex-dark">
                        <div class="ex-header">
                            <div class="ex-logo">
                                <img src="assets/img/logo.png" alt="Logo">
                            </div>
                            <span class="ex-nfc">NFC</span>
                        </div>
                        <div class="ex-body">
                            <div class="ex-name">Mehmet Yılmaz</div>
                            <div class="ex-title">CEO & Kurucu Ortak</div>
                        </div>
                        <div class="ex-footer">
                            <div class="ex-contact">
                                <span><i data-lucide="phone"></i> 0532 123 45 67</span>
                                <span><i data-lucide="mail"></i> mehmet@firma.com</span>
                            </div>
                            <div class="ex-qr">
                                <i data-lucide="qr-code"></i>
                            </div>
                        </div>
                    </div>
                    <div class="ex-item-label">Koyu Tema Tasarımı</div>
                </div>

                <!-- Açık Kartvizit -->
                <div class="ex-item">
                    <div class="ex-card-wrapper ex-light">
                        <div class="ex-header">
                            <div class="ex-logo" style="display:flex; align-items:center; justify-content:center; font-weight:900; color:var(--navy-dark); font-size:1.1rem; border:1px solid #e2e8f0; border-radius:50%; width:36px; height:36px;">
                                AK
                            </div>
                            <span class="ex-nfc" style="color:var(--navy-dark);">NFC</span>
                        </div>
                        <div class="ex-body">
                            <div class="ex-name" style="color:var(--navy-dark);">Ayşe Kara</div>
                            <div class="ex-title">MİMAR · KARA MİMARLIK</div>
                        </div>
                        <div class="ex-footer">
                            <div class="ex-contact" style="color:var(--navy-dark);">
                                <span><i data-lucide="phone"></i> 0555 987 65 43</span>
                                <span><i data-lucide="globe"></i> ayse-mimarlik.com</span>
                            </div>
                            <div class="ex-qr" style="color:var(--navy-dark);">
                                <i data-lucide="qr-code"></i>
                            </div>
                        </div>
                    </div>
                    <div class="ex-item-label">Açık Tema Tasarımı</div>
                </div>

                <!-- Dijital Profil -->
                <div class="ex-item">
                    <div class="ex-card-wrapper ex-digital">
                        <div class="ex-avatar">SÇ</div>
                        <div class="ex-digital-info">
                            <div class="ex-name">Serkan Çelik</div>
                            <div class="ex-title" style="margin-top:0.2rem;">YAZILIM GELİŞTİRİCİ</div>
                        </div>
                        <div class="ex-digital-links">
                            <span><i data-lucide="phone"></i> Ara</span>
                            <span><i data-lucide="mail"></i> E-posta</span>
                            <span><i data-lucide="linkedin"></i> LinkedIn</span>
                        </div>
                    </div>
                    <div class="ex-item-label">Dijital Profil Arayüzü</div>
                </div>

            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         PRICING SECTION
    ═══════════════════════════════════════════ -->
    <section class="pricing-section" id="fiyatlandirma">
        <div class="container">
            <div class="section-label">FİYATLANDIRMA</div>
            <h2 class="section-title">Şeffaf <span class="gold-text">Fiyatlandırma</span></h2>
            <p class="section-desc">Hesabınızı oluşturduktan sonra panelde paketleri karşılaştırıp karar verebilirsiniz</p>

            <div class="pricing-grid">
                <!-- Klasik Paket -->
                <div class="pricing-card">
                    <div class="pricing-card-header">
                        <div class="pricing-icon">
                            <i data-lucide="credit-card"></i>
                        </div>
                        <h3>Klasik</h3>
                        <p>Sadece baskı hizmeti</p>
                    </div>
                    <div class="pricing-price">
                        <span class="price-amount">799 ₺</span>
                        <span class="price-period">tek seferlik</span>
                    </div>
                    <ul class="pricing-features">
                        <li class="included"><i data-lucide="check"></i> Standart fiziksel kartvizit baskısı</li>
                        <li class="included"><i data-lucide="check"></i> 2 revize hakkı</li>
                        <li class="included"><i data-lucide="check"></i> Kurumsal logo ve temel tasarım</li>
                        <li class="included"><i data-lucide="check"></i> Ücretsiz kargo</li>
                        <li class="excluded"><i data-lucide="x"></i> Dijital profil linki</li>
                        <li class="excluded"><i data-lucide="x"></i> QR paylaşımı</li>
                    </ul>
                    <a href="auth/register.php" class="pricing-btn-outline">Hemen Başvur</a>
                </div>

                <!-- Akıllı Paket (Önerilen) -->
                <div class="pricing-card featured">
                    <div class="pricing-best-badge">EN ÇOK TERCİH EDİLEN</div>
                    <div class="pricing-card-header">
                        <div class="pricing-icon gold">
                            <i data-lucide="zap"></i>
                        </div>
                        <h3>Akıllı</h3>
                        <p>Fiziksel + Dijital kombo</p>
                    </div>
                    <div class="pricing-price">
                        <span class="price-amount">1.200 ₺</span>
                        <span class="price-period">yıllık tek ödeme</span>
                    </div>
                    <ul class="pricing-features">
                        <li class="included"><i data-lucide="check"></i> 1000 adet fiziksel kartvizit baskısı</li>
                        <li class="included"><i data-lucide="check"></i> 1 yıllık dijital panel erişimi</li>
                        <li class="included"><i data-lucide="check"></i> Dinamik QR ile bilgi güncelleme</li>
                        <li class="included"><i data-lucide="check"></i> Sosyal medya link modülü</li>
                        <li class="included"><i data-lucide="check"></i> 2 revize hakkı</li>
                        <li class="included"><i data-lucide="check"></i> Ücretsiz kargo</li>
                    </ul>
                    <a href="auth/register.php" class="pricing-btn-primary">Hemen Başvur</a>
                </div>

                <!-- Panel Paketi -->
                <div class="pricing-card">
                    <div class="pricing-card-header">
                        <div class="pricing-icon">
                            <i data-lucide="smartphone"></i>
                        </div>
                        <h3>Dijital Panel</h3>
                        <p>Sadece dijital deneyim</p>
                    </div>
                    <div class="pricing-price">
                        <span class="price-amount">700 ₺</span>
                        <span class="price-period">yıllık tek ödeme</span>
                    </div>
                    <ul class="pricing-features">
                        <li class="included"><i data-lucide="check"></i> Dijital profil ve QR linki</li>
                        <li class="included"><i data-lucide="check"></i> Sosyal link alanı</li>
                        <li class="included"><i data-lucide="check"></i> Anında yayınlanabilir profil</li>
                        <li class="included"><i data-lucide="check"></i> Panel üzerinden düzenleme</li>
                        <li class="excluded"><i data-lucide="x"></i> Fiziksel kartvizit baskısı</li>
                        <li class="excluded"><i data-lucide="x"></i> Tasarım revizyonu</li>
                    </ul>
                    <a href="auth/register.php" class="pricing-btn-outline">Hemen Başvur</a>
                </div>
            </div>
        </div>
    </section>



    <!-- ═══════════════════════════════════════════
         FOOTER CTA
    ═══════════════════════════════════════════ -->
    <section class="footer-cta" id="iletisim">
        <div class="container">
            <div class="footer-cta-box">
                <div class="fcta-glow"></div>
                <div class="fcta-content">
                    <div class="section-label light">HEMEN BAŞLAYIN</div>
                    <h2>Dijital Kartvizitinizi<br><span class="highlight-gold">Hemen Oluşturun</span></h2>
                    <p>Onbinlerce profesyonelin tercihi olan Zerosoft QR sistemiyle tanışın. Kurulum ücretsiz, ilk adım 2 dakika sürer.</p>
                    <div class="fcta-actions">
                        <a href="auth/register.php" class="btn-fcta-primary">
                            Şimdi Başvur <i data-lucide="arrow-right"></i>
                        </a>
                        <a href="auth/login.php" class="btn-fcta-ghost">Giriş Yap</a>
                    </div>
                    <div class="fcta-trust">
                        <span><i data-lucide="shield-check"></i> Güvenli SSL</span>
                        <span><i data-lucide="truck"></i> Ücretsiz Kargo</span>
                        <span><i data-lucide="refresh-cw"></i> 2 Revize Hakkı</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════ -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="f-brand">
                    <div class="footer-logo">
                        <img src="assets/img/logo.png" alt="QR Kartvizit">
                        <span>QR Kartvizit</span>
                    </div>
                    <p>Profesyonel kartvizit basımı ve dijital profil yönetimi. İşletmenizi bir QR kod ile dijital dünyaya taşıyın.</p>
                    <div class="footer-socials">
                        <a href="#" aria-label="Instagram" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                        </a>
                        <a href="#" aria-label="LinkedIn" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>
                        </a>
                        <a href="#" aria-label="Twitter" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg>
                        </a>
                        <a href="#" aria-label="WhatsApp" class="social-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </a>
                    </div>
                </div>
                <div class="f-nav">
                    <h5>ÜRÜN</h5>
                    <ul>
                        <li><a href="#ozellikler">Özellikler</a></li>
                        <li><a href="#fiyatlandirma">Fiyatlandırma</a></li>
                        <li><a href="#ornekler">Örnekler</a></li>
                        <li><a href="auth/register.php">Başvuru Yap</a></li>
                    </ul>
                </div>
                <div class="f-nav">
                    <h5>DESTEK</h5>
                    <ul>
                        <li><a href="#iletisim">İletişim</a></li>
                        <li><a href="#">Hakkımızda</a></li>
                        <li><a href="#">Gizlilik Politikası</a></li>
                        <li><a href="#">KVKK</a></li>
                    </ul>
                </div>
                <div class="f-nav">
                    <h5>HESAP</h5>
                    <ul>
                        <li><a href="auth/login.php">Giriş Yap</a></li>
                        <li><a href="auth/register.php">Kayıt Ol</a></li>
                        <li><a href="auth/register.php?plan=smart">Akıllı Paket</a></li>
                        <li><a href="auth/register.php?plan=classic">Klasik Paket</a></li>
                    </ul>
                </div>
            </div>
            <div class="f-bot">
                <p>&copy; 2026 QR Kartvizit. Tüm hakları saklıdır.</p>
                <p>Zerosoft tarafından geliştirilmiştir.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
