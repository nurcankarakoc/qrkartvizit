<?php
// processes/login_process.php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';

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

        $db_package = qrk_get_customer_package_slug($pdo, (int)$user['id']);
        $db_order_credits = qrk_get_customer_remaining_order_credits($pdo, (int)$user['id']);
        if ($db_package !== '' && $db_order_credits > 0) {
            $_SESSION['default_order_package'] = $db_package;
        } else {
            unset($_SESSION['default_order_package']);
        }

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
            $active_package = qrk_get_customer_package_slug($pdo, (int)$user['id']);
            $pending_package = qrk_get_customer_pending_package_slug($pdo, (int)$user['id']);
            if ($active_package !== '') {
                header("Location: ../customer/dashboard.php");
            } elseif ($pending_package !== '') {
                header("Location: ../customer/purchase-review.php");
            } else {
                header("Location: ../customer/packages.php");
            }
        }
        exit();
    } else {
        header("Location: ../auth/login.php?error=invalid");
        exit();
    }
}
