<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';

if (
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'admin' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header("Location: ../auth/login.php");
    exit();
}

verify_csrf_or_redirect('../admin/dashboard.php?error=csrf');

function table_exists(PDO $pdo, string $table): bool
{
    $table_escaped = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
    return (bool)$stmt->fetchColumn();
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!table_exists($pdo, $table)) {
        return false;
    }

    $table_escaped = str_replace('`', '``', $table);
    $column_escaped = str_replace("'", "''", $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_escaped}` LIKE '{$column_escaped}'");
    return (bool)$stmt->fetch();
}

$action = trim((string)($_POST['action'] ?? ''));

if ($action === 'update_order_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = trim((string)($_POST['new_status'] ?? ''));
    $allowed_statuses = [
        'pending',
        'pending_payment',
        'pending_design',
        'designing',
        'awaiting_approval',
        'revision_requested',
        'approved',
        'printing',
        'shipping',
        'completed',
    ];

    if ($order_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
        header("Location: ../admin/orders.php?msg=invalid");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        header("Location: ../admin/orders.php?msg=updated");
        exit();
    } catch (Throwable $e) {
        header("Location: ../admin/orders.php?msg=error");
        exit();
    }
}

header("Location: ../admin/dashboard.php?msg=invalid");
exit();
?>
