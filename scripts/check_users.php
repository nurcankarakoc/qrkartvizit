<?php
require_once __DIR__ . '/../core/db.php';
try {
    $stmt = $pdo->query("SELECT id, name, email, role FROM users");
    $users = $stmt->fetchAll();
    echo "Current Users:\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Name: {$user['name']} | Email: {$user['email']} | Role: {$user['role']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
