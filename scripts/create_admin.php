<?php
require_once __DIR__ . '/../core/db.php';

$name = "Zerosoft Admin";
$email = getenv('ADMIN_EMAIL') ?: "admin@zerosoft.local";
$plain_password = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(6));
$password = password_hash($plain_password, PASSWORD_DEFAULT);
$role = "admin";

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);
    echo "Admin user created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $plain_password\n";
} catch (Exception $e) {
    echo "Error creating admin: " . $e->getMessage();
}
