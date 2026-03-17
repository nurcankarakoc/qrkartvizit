<?php
require_once 'core/db.php';

$email = "admin@zerosoft.com";
$new_pass = "admin123";
$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed_pass, $email]);
    
    if ($stmt->rowCount() > 0) {
        echo "Password reset successful for $email\n";
    } else {
        // Maybe the user doesn't exist? (Unlikely based on check_users but let's be sure)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(["Zerosoft Admin", $email, $hashed_pass, "admin"]);
        echo "Admin user created (was missing) for $email\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
