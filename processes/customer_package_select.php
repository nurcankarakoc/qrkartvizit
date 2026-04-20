<?php
require_once __DIR__ . '/../core/security.php';
ensure_session_started();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/customer_access.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer'
) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../customer/packages.php');
    exit();
}

verify_csrf_or_redirect('../customer/packages.php?status=csrf');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$package_slug = qrk_normalize_package_slug((string)($_POST['package'] ?? ''));

if ($user_id <= 0 || $package_slug === '') {
    header('Location: ../customer/packages.php?status=invalid');
    exit();
}

$definitions = qrk_get_all_package_definitions($pdo);
if (!isset($definitions[$package_slug]) || !(bool)($definitions[$package_slug]['is_active'] ?? true)) {
    header('Location: ../customer/packages.php?status=invalid');
    exit();
}

if (qrk_customer_has_existing_order($pdo, $user_id)) {
    header('Location: ../customer/packages.php?status=locked');
    exit();
}

// Bypassing payment: Directly assign the package and set 1 order credit
qrk_assign_customer_package($pdo, $user_id, $package_slug, 1);

header('Location: ../customer/purchase-review.php?status=success');
exit();
