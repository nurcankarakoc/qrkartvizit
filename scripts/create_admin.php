<?php
require_once 'core/db.php';

$name = "Zerosoft Admin";
$email = "admin@zerosoft.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";

try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);
    echo "Admin user created successfully!\n";
    echo "Email: $email\n";
    echo "Password: admin123\n";
} catch (Exception $e) {
    echo "Error creating admin: " . $e->getMessage();
}
