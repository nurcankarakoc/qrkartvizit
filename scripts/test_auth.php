<?php
require_once __DIR__ . '/../core/db.php';
$email = getenv('ADMIN_EMAIL') ?: 'admin@zerosoft.local';
$password = getenv('ADMIN_PASSWORD') ?: '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    if (password_verify($password, $user['password'])) {
        echo "AUTH SUCCESS: Correct password for {$user['email']}\n";
    } else {
        echo "AUTH FAILED: Wrong password for {$user['email']}\n";
    }
} else {
    echo "AUTH FAILED: User not found.\n";
}
