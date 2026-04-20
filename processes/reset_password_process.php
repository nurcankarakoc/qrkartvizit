<?php
// processes/reset_password_process.php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../auth/login.php?error=csrf');

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$password_confirm = (string)($_POST['password_confirm'] ?? '');

// Token format kontrolü (bin2hex 32 byte = 64 hex karakter)
if ($token === '' || !preg_match('/^[0-9a-f]{64}$/i', $token)) {
    header('Location: ../auth/forgot-password.php?error=invalid_token');
    exit();
}

// Şifre boşluk temizleme ve uzunluk kontrolü
$password = trim($password);
$password_confirm = trim($password_confirm);

if ($password === '' || strlen($password) < 8 || $password !== $password_confirm) {
    $err = ($password !== $password_confirm) ? 'mismatch' : 'weak_password';
    header('Location: ../auth/reset-password.php?token=' . urlencode($token) . '&error=' . $err);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../auth/forgot-password.php?error=invalid_token');
    exit();
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
$stmt->execute([$hashed_password, $user['id']]);

header('Location: ../auth/login.php?success=reset');
exit();
