<?php
require_once '../core/security.php';
ensure_session_started();
header('Content-Type: text/html; charset=UTF-8');
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';

// Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$status = $_GET['status'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'Değerli Üyemiz';

// Prepare variables for the success view
$is_success = ($status === 'success');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Başarılı - Zerosoft QR</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --navy: #0A2F2F;
            --gold: #A6803F;
            --success: #22c55e;
        }

        body {
            margin: 0;
            height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .success-card {
            max-width: 500px;
            width: 90%;
            background: #fff;
            border-radius: 32px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 
                0 4px 6px -1px rgb(0 0 0 / 0.1), 
                0 20px 40px -1px rgb(0 0 0 / 0.05);
            border: 1px solid rgba(0,0,0,0.03);
            position: relative;
        }

        /* Golden Sparkle Effects */
        .success-card::before {
            content: '';
            position: absolute;
            top: -20px; right: -20px;
            width: 100px; height: 100px;
            background: radial-gradient(circle, rgba(166, 128, 63, 0.1) 0%, transparent 70%);
            z-index: -1;
        }

        .icon-box {
            width: 80px; height: 80px;
            background: rgba(34, 197, 94, 0.1);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--success);
            animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        h1 {
            color: var(--navy);
            font-size: 1.75rem;
            font-weight: 850;
            margin: 0 0 1rem;
            letter-spacing: -0.04em;
        }

        p {
            color: #64748b;
            line-height: 1.7;
            font-size: 1.05rem;
            margin: 0 0 2.5rem;
        }

        .btn-success {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            min-height: 56px;
            background: linear-gradient(135deg, var(--navy), #114444);
            color: #fff;
            text-decoration: none;
            border-radius: 16px;
            font-weight: 800;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 10px 20px rgba(10, 47, 47, 0.15);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(10, 47, 47, 0.2);
            filter: brightness(1.1);
        }

        .badge-success {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f0fdf4;
            color: #166534;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Background glow */
        .bg-glow {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(166, 128, 63, 0.05) 0%, transparent 70%);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>

    <div class="success-card">
        <div class="badge-success">
            <i data-lucide="shield-check" style="width:16px; height:16px;"></i>
            Güvenli İşlem Tamamlandı
        </div>

        <div class="icon-box">
            <i data-lucide="check-circle-2" style="width:48px; height:48px;"></i>
        </div>

        <h1>Ödeme Başarılı!</h1>
        
        <p>
            Tebrikler <strong><?php echo htmlspecialchars($user_name); ?></strong>, paketiniz başarıyla tanımlandı. 
            Güvenlik protokollerimiz gereği, dijital profilinizi yönetmeye başlamak için tekrar giriş yapmanız gerekmektedir.
        </p>

        <a href="../processes/logout.php" class="btn-success">
            Şimdi Giriş Yap
            <i data-lucide="log-in" style="width:20px; height:20px;"></i>
        </a>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
