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
    <title>Giriş Yap — Zerosoft</title>
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
        }

        body { background: #f8fafc; color: #1e293b; font-family: 'Inter', sans-serif; }

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

        .auth-main {
            padding: 4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }

        .form-container {
            width: 100%;
            max-width: 450px;
        }

        .form-header { margin-bottom: 3rem; text-align: left; }
        .form-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--navy-blue); }
        .form-header p { color: #64748b; font-size: 1.1rem; }

        .form-group { margin-bottom: 2rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; color: #475569; }
        
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; }
        
        .form-control {
            width: 100%;
            padding: 1rem 1.2rem 1rem 3.2rem;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--gold);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(166, 128, 63, 0.1);
        }

        .btn-auth-submit {
            width: 100%;
            background: var(--navy-blue);
            color: #fff;
            padding: 1.2rem;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn-auth-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            background: var(--navy-dark);
        }

        .auth-nav {
            position: absolute;
            top: 2rem;
            left: 2rem;
        }

        .back-link {
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
        }

        .back-link:hover { color: #fff; }

        @media (max-width: 1024px) {
            .auth-layout { grid-template-columns: 1fr; }
            .auth-sidebar { display: none; }
            .auth-main { padding: 3rem 1.5rem; }
        }

        @media (max-width: 768px) {
            .auth-main {
                align-items: flex-start;
                padding: 1.5rem 1rem calc(2rem + env(safe-area-inset-bottom, 0px));
            }
            .form-header h1 { font-size: 2rem; }
            .btn-auth-submit { min-height: 44px; }
        }

        @media (max-width: 480px) {
            .form-container { max-width: 100%; }
            .form-header h1 { font-size: 1.75rem; }
            .form-group { margin-bottom: 1.25rem; }
        }
    </style>
</head>
<body>

    <div class="auth-layout">
        <div class="auth-sidebar">
            <div class="auth-nav">
                <a href="../index.php" class="back-link">
                    <i data-lucide="arrow-left"></i> Anasayfaya Dön
                </a>
            </div>
            <div class="auth-sidebar-content">
                <h2>Profilinizi <br>Yönetmeye <br>Devam Edin</h2>
                <p style="opacity: 0.7; font-size: 1.1rem; margin-top: 1.5rem;">Dijital kartvizit bilgilerinizi güncelleyebilir ve sipariş durumunuzu takip edebilirsiniz.</p>
                <div style="margin-top: 4rem; width: 60px; height: 6px; background: var(--gold); border-radius: 10px;"></div>
            </div>
        </div>

        <main class="auth-main">
            <div class="form-container">
                <div class="form-header">
                    <h1>Giriş Yap</h1>
                    <p>Lütfen bilgilerinizi girerek oturum açın.</p>
                </div>

                <?php if(isset($_GET['error'])): ?>
                    <div style="background: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 1rem; border-radius: 12px; margin-bottom: 2rem; font-weight: 600; font-size: 0.9rem;">
                        <?php 
                            if($_GET['error'] == 'invalid') echo 'E-posta veya şifre hatalı!';
                            elseif($_GET['error'] == 'empty') echo 'Lütfen tüm alanları doldurun.';
                            else echo 'Bir hata oluştu. Lütfen tekrar deneyin.';
                        ?>
                    </div>
                <?php endif; ?>

                <form action="../processes/login_process.php" method="POST">
                    <?php echo csrf_input(); ?>
                    <div class="form-group">
                        <label>E-posta Adresi</label>
                        <div class="input-group">
                            <i data-lucide="mail"></i>
                            <input type="email" name="email" class="form-control" placeholder="Örn: mehmet@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label>Şifre</label>
                            <a href="#" style="font-size: 0.8rem; color: var(--gold); font-weight: 700; text-decoration: none; margin-bottom: 0.5rem;">Şifremi Unuttum?</a>
                        </div>
                        <div class="input-group">
                            <i data-lucide="lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        Oturum Aç <i data-lucide="log-in" style="vertical-align: middle; margin-left: 0.5rem; width: 20px;"></i>
                    </button>

                    <p style="text-align: center; margin-top: 2.5rem; font-size: 0.95rem; color: #64748b;">
                        Hesabınız yok mu? <a href="register.php" style="color: var(--gold); font-weight: 800; text-decoration: none;">Hemen Başvuru Yap</a>
                    </p>
                </form>
            </div>
        </main>
    </div>

    <script src="../assets/js/mobile-form.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
