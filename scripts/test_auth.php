<?php
require_once 'core/db.php';
$email = 'admin@zerosoft.com';
$password = 'admin123';

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
