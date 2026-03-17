<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mevcut profil bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    // Profil yoksa taslak oluştur (slug email'den türetilebilir)
    $slug = strtolower(str_replace(' ', '-', explode('@', $_SESSION['user_name'] ?? 'user')[0])) . '-' . rand(100, 999);
    $stmt = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $slug, $_SESSION['user_name'] ?? 'User']);
    
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$pdo->lastInsertId()]);
    $profile = $stmt->fetch();
}

// Sosyal medya linklerini çek
$stmt = $pdo->prepare("SELECT * FROM social_links WHERE profile_id = ?");
$stmt->execute([$profile['id']]);
$links = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilimi Düzenle — Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #0A2F2F;
            --content-bg: #f8fafc;
            --gold: #A6803F;
            --navy-blue: #0A2F2F;
            --navy-dark: #072424;
        }

        body { background: var(--content-bg); display: flex; min-height: 100vh; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 3rem; }

        .card { background: #fff; border-radius: 20px; padding: 2.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 0.5rem; color: #475569; }
        .form-control { width: 100%; padding: 0.8rem 1.2rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; background: #f8fafc; transition: 0.3s; }
        .form-control:focus { border-color: var(--gold); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(166, 128, 63, 0.1); }

        .image-upload { width: 120px; height: 120px; border-radius: 50%; border: 2px dashed #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden; margin-bottom: 2rem; }
        .image-upload img { width: 100%; height: 100%; object-fit: cover; position: absolute; }
        .image-upload i { color: #94a3b8; }

        .social-link-row { display: grid; grid-template-columns: 150px 1fr auto; gap: 1rem; margin-bottom: 1rem; }

        .btn-save { background: var(--navy-blue); color: #fff; border: none; padding: 1rem 2.5rem; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-save:hover { background: var(--navy-dark); transform: translateY(-2px); }

        .menu-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; color: rgba(255,255,255,0.6); text-decoration: none; border-radius: 12px; margin-bottom: 0.5rem; transition: all 0.3s; font-weight: 500; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.1); color: #fff; }
        .menu-item i { width: 20px; height: 20px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-logotype">
                <div class="mock-logo">Z</div>
                <span>Zerosoft <small>Panel</small></span>
            </div>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i data-lucide="layout-dashboard"></i><span>Genel Bakış</span></a>
            <a href="profile.php" class="menu-item active"><i data-lucide="user-cog"></i><span>Profilimi Düzenle</span></a>
            <a href="design-tracking.php" class="menu-item"><i data-lucide="palette"></i><span>Tasarım Süreci</span></a>
            <a href="#" class="menu-item"><i data-lucide="shopping-bag"></i><span>Siparişlerim</span></a>
        </nav>
    </aside>

    <main class="main-content">
        <header style="margin-bottom: 3rem;">
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--navy-blue);">Profilimi Düzenle</h1>
            <p style="color: #64748b;">Dijital kartvizitinizde görünecek olan tüm bilgileri buradan güncelleyebilirsiniz.</p>
        </header>

        <form action="../processes/profile_update.php" method="POST" enctype="multipart/form-data">
            <div class="card">
                <div class="form-group">
                    <label>Profil Fotoğrafı</label>
                    <div class="image-upload" onclick="document.getElementById('photo').click()">
                        <?php if($profile['photo_path']): ?>
                            <img src="<?php echo $profile['photo_path']; ?>" alt="Profile">
                        <?php else: ?>
                            <i data-lucide="camera" style="width: 32px; height: 32px;"></i>
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;">Yükle</span>
                        <?php endif; ?>
                        <input type="file" id="photo" name="photo" hidden onchange="previewImage(this)">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label>Ad Soyad</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name']); ?>" placeholder="Mehmet Yılmaz">
                    </div>
                    <div class="form-group">
                        <label>Mesleki Unvan</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($profile['title']); ?>" placeholder="Yazılım Geliştirici">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label>Şirket Adı</label>
                        <input type="text" name="company" class="form-control" value="<?php echo htmlspecialchars($profile['company']); ?>" placeholder="Zerosoft Teknoloji">
                    </div>
                    <div class="form-group">
                        <label>İş Telefonu</label>
                        <input type="tel" name="phone_work" class="form-control" value="<?php echo htmlspecialchars($profile['phone_work']); ?>" placeholder="0532 ...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Kısa Biyografi / Hakkında</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Örn: 10 yıllık deneyime sahip dijital pazarlama uzmanı..."><?php echo htmlspecialchars($profile['bio']); ?></textarea>
                </div>

                <h3 style="font-weight: 800; font-size: 1.25rem; margin: 2.5rem 0 1.5rem; color: var(--navy-blue);">Sosyal Medya & Linkler</h3>
                
                <div id="social-links-container">
                    <?php if(empty($links)): ?>
                        <div class="social-link-row">
                            <select name="platforms[]" class="form-control">
                                <option value="instagram">Instagram</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="website">Web Sitesi</option>
                                <option value="mail">E-posta</option>
                            </select>
                            <input type="text" name="urls[]" class="form-control" placeholder="Linkinizi buraya yapıştırın...">
                            <button type="button" class="btn-save" style="background:#f1f5f9; color:#ef4444; padding:0.8rem; height: auto;" onclick="removeRow(this)">Sil</button>
                        </div>
                    <?php else: ?>
                        <?php foreach($links as $link): ?>
                            <div class="social-link-row">
                                <select name="platforms[]" class="form-control">
                                    <option value="instagram" <?php echo $link['platform'] == 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                                    <option value="whatsapp" <?php echo $link['platform'] == 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                                    <option value="linkedin" <?php echo $link['platform'] == 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                                    <option value="website" <?php echo $link['platform'] == 'website' ? 'selected' : ''; ?>>Web Sitesi</option>
                                    <option value="mail" <?php echo $link['platform'] == 'mail' ? 'selected' : ''; ?>>E-posta</option>
                                </select>
                                <input type="text" name="urls[]" class="form-control" value="<?php echo htmlspecialchars($link['url']); ?>" placeholder="Linkinizi buraya yapıştırın...">
                                <button type="button" class="btn-save" style="background:#f1f5f9; color:#ef4444; padding:0.8rem; height: auto;" onclick="removeRow(this)">Sil</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" onclick="addSocialRow()" style="background: rgba(166, 128, 63, 0.1); color: var(--gold); border:none; padding: 0.8rem 1.5rem; border-radius:12px; font-weight:700; cursor:pointer; margin-bottom: 2.5rem; margin-top: 1rem;">+ Yeni Link Ekle</button>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 2rem; display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-save">Değişiklikleri Kaydet</button>
                </div>
            </div>
        </form>
    </main>

    <script>
        lucide.createIcons();

        function addSocialRow() {
            const container = document.getElementById('social-links-container');
            const row = document.createElement('div');
            row.className = 'social-link-row';
            row.innerHTML = `
                <select name="platforms[]" class="form-control">
                    <option value="instagram">Instagram</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="linkedin">LinkedIn</option>
                    <option value="website">Web Sitesi</option>
                    <option value="mail">E-posta</option>
                </select>
                <input type="text" name="urls[]" class="form-control" placeholder="Linkinizi buraya yapıştırın...">
                <button type="button" class="btn-save" style="background:#f1f5f9; color:#ef4444; padding:0.8rem; height: auto;" onclick="removeRow(this)">Sil</button>
            `;
            container.appendChild(row);
        }

        function removeRow(btn) {
            btn.parentElement.remove();
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let img = document.querySelector('.image-upload img');
                    if (!img) {
                        img = document.createElement('img');
                        document.querySelector('.image-upload').innerHTML = '';
                        document.querySelector('.image-upload').appendChild(img);
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
