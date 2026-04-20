<?php
// processes/forgot_password.php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/mailer.php';
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../auth/login.php?error=csrf');

$email = trim((string)($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../auth/forgot-password.php?error=invalid_email');
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);
    
    qrk_send_password_reset_email($email, $token);
}

// Security note: We tell the user "Link sent if email exists" to find out if account is registered.
header('Location: ../auth/forgot-password.php?success=1');
exit();
