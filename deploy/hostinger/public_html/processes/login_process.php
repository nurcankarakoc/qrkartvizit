<?php
// processes/login_process.php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_or_redirect('../auth/login.php?error=csrf');

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: ../auth/login.php?error=empty");
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
            header("Location: ../auth/login.php?error=inactive");
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        try {
            $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int)$user['id']]);
        } catch (Throwable $e) {
            // no-op: login should continue even if this optional column does not exist
        }

        if ($user['role'] == 'admin') {
            header("Location: ../admin/dashboard.php");
        } elseif ($user['role'] == 'designer') {
            header("Location: ../designer/dashboard.php");
        } else {
            header("Location: ../customer/dashboard.php");
        }
        exit();
    } else {
        header("Location: ../auth/login.php?error=invalid");
        exit();
    }
}
