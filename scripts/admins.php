<?php
require_once __DIR__ . '/../core/db.php';

$users = [
    [
        getenv('ADMIN_NAME') ?: 'Admin',
        getenv('ADMIN_EMAIL') ?: 'admin@zerosoft.local',
        getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(6))
    ]
];

foreach ($users as $u) {
    $name = $u[0];
    $email = $u[1];
    $pass = $u[2];
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
            $stmt->execute([$hashed, $email]);
            echo "Updated $email with password: $pass\n";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$name, $email, $hashed]);
            echo "Created $email with password: $pass\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
