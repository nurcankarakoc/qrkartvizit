<?php
// auth/forgot-password.php
require_once '../core/db.php';
require_once '../core/security.php';
ensure_session_started();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --navy-blue: #0A2F2F; --navy-dark: #072424; --gold: #A6803F; }
        body { background: #f8fafc; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 2rem; }
        .auth-card { background: #fff; width: 100%; max-width: 450px; padding: 3rem; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); }
        .form-header { text-align: center; margin-bottom: 2.5rem; }
        .form-header h1 { font-size: 2rem; font-weight: 800; color: var(--navy-blue); margin-bottom: 0.5rem; }
        .form-header p { color: #64748b; font-size: 0.95rem; line-height: 1.5; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; color: #475569; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 18px; }
        .form-control { width: 100%; padding: 0.9rem 1.2rem 0.9rem 2.8rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem; transition: 0.3s; background: #f8fafc; }
        .form-control:focus { outline: none; border-color: var(--gold); background: #fff; box-shadow: 0 0 0 4px rgba(166,128,63,0.1); }
        .btn-submit { width: 100%; background: var(--navy-blue); color: #fff; padding: 1.1rem; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.3s; margin-top: 1rem; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); background: var(--navy-dark); }
        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #ef4444; }
        .back-to-login { display: block; text-align: center; margin-top: 2rem; color: var(--gold); font-weight: 700; text-decoration: none; font-size: 0.95rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="form-header">
            <h1>Şifremi Unuttum</h1>
            <p>E-posta adresinizi girin, şifre sıfırlama bağlantısını gönderelim.</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">Sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen gelen kutunuzu kontrol edin.</div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error">Lütfen geçerli bir e-posta adresi girin.</div>
        <?php endif; ?>

        <form action="../processes/forgot_password.php" method="POST">
            <?php echo csrf_input(); ?>
            <div class="form-group">
                <label>E-posta Adresi</label>
                <div class="input-group">
                    <i data-lucide="mail"></i>
                    <input type="email" name="email" class="form-control" placeholder="Örn: mehmet@email.com" required>
                </div>
            </div>
            <button type="submit" class="btn-submit">Sıfırlama Linki Gönder</button>
        </form>

        <a href="login.php" class="back-to-login">← Giriş Sayfasına Dön</a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
