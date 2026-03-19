<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

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

function project_base_url_for_customer_panel(): string
{
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/customer/profile.php');
    $project_path = preg_replace('#/customer/[^/]+$#', '', $script_name);

    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Mevcut profil bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    // Profil yoksa taslak oluştur (slug email'den türetilebilir)
    $seed_name = (string)($user['name'] ?? ($_SESSION['user_name'] ?? 'user'));
    $slug_base = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $seed_name), '-'));
    if ($slug_base === '') {
        $slug_base = 'user';
    }
    $slug = $slug_base . '-' . rand(100, 999);
    $stmt = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $slug, $seed_name !== '' ? $seed_name : 'User']);
    
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$pdo->lastInsertId()]);
    $profile = $stmt->fetch();
}

// Sosyal medya linklerini çek
$stmt = $pdo->prepare("SELECT package FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$order = $stmt->fetch();
$is_digital_profile_active_for_package = has_digital_profile_package((string)($order['package'] ?? ''));
$profile_slug = trim((string)($profile['slug'] ?? ''));
$public_profile_url = '';

if ($is_digital_profile_active_for_package && $profile_slug !== '') {
    $public_profile_url = project_base_url_for_customer_panel()
        . '/kartvizit.php?slug='
        . rawurlencode($profile_slug);
}

$stmt = $pdo->prepare("SELECT * FROM social_links WHERE profile_id = ?");
$stmt->execute([$profile['id']]);
$links = $stmt->fetchAll();

$social_platform_options = [
    ['value' => 'instagram', 'label' => 'Instagram', 'icon' => 'instagram'],
    ['value' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'linkedin'],
    ['value' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'message-circle'],
    ['value' => 'x', 'label' => 'X / Twitter', 'icon' => 'twitter'],
    ['value' => 'telegram', 'label' => 'Telegram', 'icon' => 'send'],
    ['value' => 'youtube', 'label' => 'YouTube', 'icon' => 'youtube'],
    ['value' => 'facebook', 'label' => 'Facebook', 'icon' => 'facebook'],
    ['value' => 'tiktok', 'label' => 'TikTok', 'icon' => 'music'],
    ['value' => 'website', 'label' => 'Web Sitesi', 'icon' => 'globe'],
    ['value' => 'mail', 'label' => 'E-posta', 'icon' => 'mail'],
    ['value' => 'phone', 'label' => 'Telefon', 'icon' => 'phone'],
    ['value' => 'maps', 'label' => 'Harita', 'icon' => 'map-pin'],
    ['value' => '__custom__', 'label' => 'Diğer', 'icon' => 'link-2'],
];

$social_platform_values = array_column($social_platform_options, 'value');
$resolved_brand_color = '#0A2F2F';
if (!empty($profile['brand_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['brand_color'])) {
    $resolved_brand_color = strtoupper((string)$profile['brand_color']);
} elseif (!empty($profile['theme_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$profile['theme_color'])) {
    $resolved_brand_color = strtoupper((string)$profile['theme_color']);
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilimi Düzenle — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .profile-hero { position: relative; margin-bottom: 4rem; }
        .cover-upload { width: 100%; height: 220px; border-radius: 24px; background: var(--navy-blue); border: 2px solid transparent; overflow: hidden; cursor: pointer; transition: 0.3s; position: relative; }
        .cover-upload::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.1;
            background-image: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='1' fill-rule='evenodd'%3E%3Ccircle cx='3' cy='3' r='1'/%3E%3C/g%3E%3C/svg%3E");
            z-index: 1;
        }
        .cover-upload img { width: 100%; height: 100%; object-fit: cover; position: relative; z-index: 2; }
        .cover-upload:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .cover-upload .upload-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; z-index: 3; color: #fff; font-weight: 800; gap: 0.5rem; }
        .cover-upload:hover .upload-overlay { opacity: 1; }
        
        .avatar-upload-container { position: absolute; bottom: -30px; left: 30px; }
        .avatar-upload { width: 120px; height: 120px; border-radius: 20px; background: #fff; border: 4px solid #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); cursor: pointer; overflow: hidden; position: relative; transition: 0.3s; }
        .avatar-upload img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-upload:hover { transform: translateY(-3px); }
        
        .form-section { padding: 2rem; background: #fff; border-radius: 20px; border: 1px solid #eef2f6; margin-bottom: 2rem; }
        .section-title { font-size: 1.15rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .section-title i { color: var(--gold); width: 20px; }
        
        .grid-forms { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem 2rem; }
        .form-field { margin-bottom: 1.25rem; }
        .form-field label { display: block; font-weight: 700; font-size: 0.82rem; color: #64748b; margin-bottom: 0.5rem; }
        .premium-input { width: 100%; padding: 0.9rem 1.1rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; color: var(--navy-blue); font-weight: 500; background: #fcfdfe; transition: 0.2s; }
        .premium-input:focus { border-color: var(--navy-blue); background: #fff; outline: none; }
        
        .social-link-item { 
            display: flex;
            align-items: center;
            gap: 1.25rem; 
            background: #fcfdfe; 
            padding: 1.25rem; 
            border-radius: 16px; 
            margin-bottom: 1rem; 
            border: 1px solid #eef2f6; 
            transition: 0.2s;
        }
        .social-link-item:hover { border-color: #e2e8f0; background: #fff; }
        .social-icon-wrapper {
            width: 48px;
            height: 48px;
            background: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy-blue);
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .social-link-item select {
            width: 140px;
            border: 1px solid #e2e8f0;
            background: #fff;
            font-weight: 700;
            color: var(--navy-blue);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .social-link-content { flex: 1; }
        
        .btn-remove { background: #fee2e2; color: #ef4444; border: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-remove:hover { background: #ef4444; color: #fff; }
        
        .color-picker-box { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0; }

        .btn-save-fab { position: fixed; bottom: 2rem; right: 2rem; background: var(--navy-blue); color: #fff; border: none; padding: 1rem 2rem; border-radius: 14px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.1); z-index: 500; display: flex; align-items: center; gap: 0.75rem; }
        .btn-save-fab:hover { background: #000; transform: translateY(-5px); }

        @media (max-width: 1024px) {
            .grid-forms { grid-template-columns: 1fr; }
            .social-link-item { flex-direction: column; align-items: flex-start; }
            .social-link-item select { width: 100%; }
            .share-card { flex-direction: column; text-align: center; padding: 2.5rem; gap: 2.5rem; }
            .qr-placeholder { transform: rotate(0); }
            .avatar-upload-container { left: 50%; transform: translateX(-50%); }
        }
    </style>
</head>
<body class="dashboard-body">

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Genel Bakış</a></li>
                <li class="active"><a href="profile.php"><i data-lucide="user-cog"></i> Profilimi Düzenle</a></li>
                <li><a href="design-tracking.php"><i data-lucide="palette"></i> Tasarım Süreci</a></li>
                <li><a href="#"><i data-lucide="shopping-bag"></i> Siparişlerim</a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)); ?></div>
                <div class="details">
                    <span class="name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Müşteri'); ?></span>
                    <span class="role">Premium Üye</span>
                </div>
            </div>
            <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-bar">
            <div>
                <h1>Profilini Düzenle</h1>
                <p style="color: #64748b; margin-top: 0.5rem;">Dijital dünyadaki vitrinini özelleştir.</p>
            </div>
        </header>

        <div class="content-wrapper">
            
            <?php if ($public_profile_url !== ''): ?>
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 2.5rem; display: flex; align-items: center; justify-content: space-between; gap: 3rem; margin-bottom: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
                    <div style="flex: 1;">
                        <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 0.5rem; letter-spacing: -0.5px;">Dijital Kartınız Hazır! 🚀</h2>
                        <p style="color: #64748b; font-weight: 500; font-size: 0.95rem; margin-bottom: 1.5rem;">Paylaşım seçeneklerini kullanarak profilinizi yaygınlaştırın.</p>
                        <div style="display: flex; background: #f8fafc; padding: 0.4rem; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                            <span style="flex: 1; padding: 0.6rem 1rem; font-size: 0.85rem; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600;" id="shareUrl"><?php echo htmlspecialchars($public_profile_url); ?></span>
                            <button type="button" style="background: var(--navy-blue); color: #fff; border: none; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: 0.2s;" onclick="copyToClipboard()">Kopyala</button>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="https://wa.me/?text=<?php echo urlencode('Dijital kartvizitimi inceleyin: ' . $public_profile_url); ?>" target="_blank" style="background:#f1f5f9; color:#1e293b; padding:0.6rem 1.2rem; border-radius:12px; text-decoration:none; font-weight:700; display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; transition:0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                <i data-lucide="message-circle" style="width:18px;"></i> WhatsApp
                            </a>
                            <a href="mailto:?subject=Dijital Kartvizitim&body=<?php echo urlencode($public_profile_url); ?>" style="background:#f1f5f9; color:#1e293b; padding:0.6rem 1.2rem; border-radius:12px; text-decoration:none; font-weight:700; display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; transition:0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                                <i data-lucide="mail" style="width:18px;"></i> E-posta
                            </a>
                        </div>
                    </div>
                    <div style="background: #fff; padding: 1rem; border-radius: 16px; border: 1px solid #eef2f6; text-align: center;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($public_profile_url); ?>" alt="QR Code" style="width: 140px; height: 140px; display: block; border-radius: 8px;">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=1000x1000&data=<?php echo urlencode($public_profile_url); ?>" download="QR_Kartvizit" target="_blank" style="display:block; margin-top:0.75rem; color:#64748b; font-weight:700; text-decoration:none; font-size:0.75rem;">
                            <i data-lucide="download" style="width:14px; vertical-align:middle; margin-right:4px;"></i> QR İndir
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <form action="../processes/profile_update.php" method="POST" enctype="multipart/form-data" id="profileForm">
                <?php echo csrf_input(); ?>
                
                <div class="profile-hero">
                    <div class="cover-upload" onclick="document.getElementById('cover_photo').click()">
                        <?php if(!empty($profile['cover_photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($profile['cover_photo']); ?>" id="coverPreview">
                        <?php else: ?>
                            <div style="display:flex; flex-direction:column; align-items:center; gap:0.5rem;">
                                <i data-lucide="image" style="width: 48px; height: 48px; color: #cbd5e1;"></i>
                                <span style="font-weight:700; color:#94a3b8;">Kapak Fotoğrafı Ekle</span>
                            </div>
                        <?php endif; ?>
                        <div class="upload-overlay">
                            <i data-lucide="camera" style="color:#fff; width:28px; height:28px;"></i>
                            <span style="font-weight:800; font-size:0.95rem;">Kapak Fotoğrafı Değiştir</span>
                        </div>
                        <input type="file" id="cover_photo" name="cover_photo" hidden onchange="previewFile(this, 'coverPreview')" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="avatar-upload-container">
                        <div class="avatar-upload" onclick="document.getElementById('photo').click()">
                            <?php if(!empty($profile['photo_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($profile['photo_path']); ?>" id="avatarPreview">
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f1f5f9;">
                                    <i data-lucide="user" style="width:48px; height:48px; color:#cbd5e1;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="upload-overlay"><i data-lucide="camera" style="color:#fff; width:24px; height:24px;"></i></div>
                            <input type="file" id="photo" name="photo" hidden onchange="previewFile(this, 'avatarPreview')" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2.5rem;">
                        <h3 class="section-title" style="margin-bottom:0;"><i data-lucide="user"></i> Temel Bilgiler</h3>
                        <?php if ($public_profile_url !== ''): ?>
                            <a href="<?php echo htmlspecialchars($public_profile_url); ?>" target="_blank" style="color:var(--gold); font-weight:900; text-decoration:none; font-size:1rem; display:flex; align-items:center; gap:0.6rem; background:rgba(166,128,63,0.08); padding:0.6rem 1.25rem; border-radius:14px; transition:0.3s;" onmouseover="this.style.background='rgba(166,128,63,0.15)'">
                                Canlı Profiline Git <i data-lucide="external-link" style="width:18px;"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="grid-forms">
                        <div class="form-field">
                            <label>Ad Soyad</label>
                            <input type="text" name="full_name" class="premium-input" value="<?php echo htmlspecialchars($profile['full_name']); ?>" placeholder="Adınız Soyadınız">
                        </div>
                        <div class="form-field">
                            <label>Mesleki Unvan</label>
                            <input type="text" name="title" class="premium-input" value="<?php echo htmlspecialchars($profile['title']); ?>" placeholder="Yazılım Geliştirici, CEO, vb.">
                        </div>
                        <div class="form-field">
                            <label>Şirket Adı</label>
                            <input type="text" name="company" class="premium-input" value="<?php echo htmlspecialchars($profile['company']); ?>" placeholder="Zerosoft Teknoloji">
                        </div>
                        <div class="form-field">
                            <label>İş Telefonu</label>
                            <input type="tel" name="phone_work" class="premium-input" value="<?php echo htmlspecialchars($profile['phone_work'] ?: ''); ?>" placeholder="05xx xxx xx xx">
                        </div>
                    </div>
                    <div class="form-field" style="margin-bottom:0;">
                        <label>Biyografi / Hakkında</label>
                        <textarea name="bio" class="premium-input" rows="4" style="resize:none;" placeholder="Kendinizden kısaca bahsedin..."><?php echo htmlspecialchars($profile['bio']); ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title"><i data-lucide="palette"></i> Marka Kimliği</h3>
                    <div class="color-picker-box">
                        <div style="flex:1;">
                            <p style="font-weight:800; color: var(--navy-blue); margin-bottom: 0.35rem; font-size: 1.1rem;">Kişisel Marka Rengi</p>
                            <p style="font-size:0.9rem; color:#64748b; font-weight:500;">Online kartınızın ana vurgu rengini belirleyin.</p>
                        </div>
                        <div style="display:flex; align-items:center; gap:1.25rem;">
                            <input type="color" id="brand_color" name="brand_color" value="<?php echo htmlspecialchars($resolved_brand_color); ?>" style="width:64px; height:64px; border:none; border-radius:18px; cursor:pointer; background:none; padding:0; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                            <input type="text" id="brand_color_hex" maxlength="7" value="<?php echo htmlspecialchars($resolved_brand_color); ?>" class="premium-input" style="width:120px; text-align:center; padding:0.85rem; font-family:monospace;" oninput="syncColorPicker(this)">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title"><i data-lucide="share-2"></i> Sosyal Medya & Linkler</h3>
                    <div id="social-links-container">
                        <?php if(empty($links)): ?>
                            <div class="social-link-item">
                                <div class="social-icon-wrapper">
                                    <i data-lucide="instagram"></i>
                                </div>
                                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                    <?php foreach ($social_platform_options as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-icon="<?php echo $opt['icon']; ?>" <?php echo $opt['value'] === 'instagram' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($opt['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="social-link-content">
                                    <input type="text" name="urls[]" class="premium-input" placeholder="Linkinizi veya kullanıcı adınızı yapıştırın...">
                                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;" disabled>
                                </div>
                                <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
                            </div>
                        <?php else: ?>
                            <?php foreach($links as $link): ?>
                                <?php
                                    $platform_raw = strtolower(trim((string)($link['platform'] ?? '')));
                                    $is_custom_platform = !in_array($platform_raw, $social_platform_values, true);
                                    $selected_platform = $is_custom_platform ? '__custom__' : $platform_raw;
                                    $custom_platform_value = $is_custom_platform ? $platform_raw : '';
                                    
                                    $platform_info = array_filter($social_platform_options, fn($o) => $o['value'] === $selected_platform);
                                    $icon = !empty($platform_info) ? reset($platform_info)['icon'] : 'link-2';
                                ?>
                                <div class="social-link-item">
                                    <div class="social-icon-wrapper">
                                        <i data-lucide="<?php echo $icon; ?>"></i>
                                    </div>
                                    <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                                        <?php foreach ($social_platform_options as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt['value']); ?>" data-icon="<?php echo $opt['icon']; ?>" <?php echo $opt['value'] === $selected_platform ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="social-link-content">
                                        <input type="text" name="urls[]" class="premium-input" value="<?php echo htmlspecialchars($link['url']); ?>" placeholder="Linkinizi buraya yapıştırın...">
                                        <input type="text" name="platform_customs[]" class="premium-input" value="<?php echo htmlspecialchars($custom_platform_value); ?>" <?php echo $is_custom_platform ? '' : 'style="display:none; margin-top:0.75rem;" disabled'; ?>>
                                    </div>
                                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="addSocialLink()" class="mini-btn" style="width:100%; border-style:dashed; margin-top:1.5rem; padding:1.5rem; border-color:var(--gold); color:var(--gold); font-size:1.05rem;">
                        <i data-lucide="plus-circle" style="width:20px; vertical-align:middle; margin-right:8px;"></i> Yeni Bağlantı Ekle
                    </button>
                </div>

                <div style="height: 120px;"></div>
                
                <button type="submit" class="btn-save-fab">
                    Değişiklikleri Kaydet <i data-lucide="save"></i>
                </button>
            </form>
        </div>
    </main>

    <script src="../assets/js/dashboard-mobile.js"></script>
    <script>
        lucide.createIcons();
        
        const SOCIAL_OPTIONS = <?php echo json_encode($social_platform_options); ?>;

        function previewFile(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    let preview = document.getElementById(previewId);
                    if (!preview) {
                        const parent = input.parentElement;
                        const newImg = document.createElement('img');
                        newImg.id = previewId;
                        parent.prepend(newImg);
                        preview = newImg;
                    }
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function syncColorPicker(input) {
            if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                document.getElementById('brand_color').value = input.value;
            }
        }
        document.getElementById('brand_color').addEventListener('input', (e) => {
            document.getElementById('brand_color_hex').value = e.target.value.toUpperCase();
        });

        function handlePlatformChange(select) {
            const row = select.closest('.social-link-item');
            const customInput = row.querySelector('input[name="platform_customs[]"]');
            const iconWrapper = row.querySelector('.social-icon-wrapper');
            
            // Icon update
            const selectedOption = select.options[select.selectedIndex];
            const iconName = selectedOption.getAttribute('data-icon') || 'link-2';
            iconWrapper.innerHTML = `<i data-lucide="${iconName}"></i>`;
            lucide.createIcons();

            if (select.value === '__custom__') {
                customInput.style.display = 'block';
                customInput.disabled = false;
            } else {
                customInput.style.display = 'none';
                customInput.disabled = true;
            }
        }

        function addSocialLink() {
            const container = document.getElementById('social-links-container');
            const div = document.createElement('div');
            div.className = 'social-link-item';
            
            let optionsHtml = SOCIAL_OPTIONS.map(opt => 
                `<option value="${opt.value}" data-icon="${opt.icon}">${opt.label}</option>`
            ).join('');

            div.innerHTML = `
                <div class="social-icon-wrapper">
                    <i data-lucide="instagram"></i>
                </div>
                <select name="platforms[]" class="premium-input" onchange="handlePlatformChange(this)">
                    ${optionsHtml}
                </select>
                <div class="social-link-content">
                    <input type="text" name="urls[]" class="premium-input" placeholder="URL veya kullanıcı adı...">
                    <input type="text" name="platform_customs[]" class="premium-input" placeholder="Özel platform adı..." style="display:none; margin-top:0.75rem;" disabled>
                </div>
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()"><i data-lucide="trash-2"></i></button>
            `;
            container.appendChild(div);
            lucide.createIcons();
        }

        function copyToClipboard() {
            const url = document.getElementById('shareUrl').textContent;
            navigator.clipboard.writeText(url).then(() => {
                const btn = document.querySelector('.btn-copy');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="check" style="width:16px; margin-right:5px; vertical-align:middle;"></i> Kopyalandı!';
                btn.style.background = '#10b981';
                lucide.createIcons();
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '';
                    lucide.createIcons();
                }, 2000);
            });
        }
    </script>
</body>
</html>
