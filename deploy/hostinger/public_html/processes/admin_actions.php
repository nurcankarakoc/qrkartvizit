<?php
session_start();
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
$admin_id = (int)($_SESSION['user_id'] ?? 0);

if ($action === 'add_designer') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $raw_password = (string)($_POST['password'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($raw_password) < 6) {
        header("Location: ../admin/designers.php?msg=invalid");
        exit();
    }

    $password = password_hash($raw_password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'designer')");
        $stmt->execute([$name, $email, $password]);
        header("Location: ../admin/designers.php?msg=added");
        exit();
    } catch (Throwable $e) {
        header("Location: ../admin/designers.php?msg=exists");
        exit();
    }
}

if ($action === 'resolve_dispute') {
    $dispute_id = (int)($_POST['dispute_id'] ?? 0);
    $resolution = trim((string)($_POST['resolution'] ?? ''));
    $admin_note = trim((string)($_POST['admin_note'] ?? ''));

    if ($dispute_id <= 0 || !in_array($resolution, ['favor_customer', 'favor_designer'], true)) {
        header("Location: ../admin/disputes.php?msg=invalid");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt_info = $pdo->prepare("SELECT id, order_id, status FROM disputes WHERE id = ? FOR UPDATE");
        $stmt_info->execute([$dispute_id]);
        $dispute = $stmt_info->fetch();
        if (!$dispute) {
            throw new RuntimeException('dispute_not_found');
        }

        $status = $resolution === 'favor_customer' ? 'resolved_favor_customer' : 'resolved_favor_designer';

        $update_fields = ['status = ?', 'resolution_date = CURRENT_TIMESTAMP'];
        $update_params = [$status];

        if (table_has_column($pdo, 'disputes', 'admin_id')) {
            $update_fields[] = 'admin_id = ?';
            $update_params[] = $admin_id;
        }
        if (table_has_column($pdo, 'disputes', 'admin_note')) {
            $update_fields[] = 'admin_note = ?';
            $update_params[] = $admin_note !== '' ? $admin_note : null;
        }

        $update_params[] = $dispute_id;
        $stmt = $pdo->prepare("UPDATE disputes SET " . implode(', ', $update_fields) . " WHERE id = ?");
        $stmt->execute($update_params);

        $order_id = (int)$dispute['order_id'];
        if ($order_id > 0) {
            if ($resolution === 'favor_customer') {
                $pdo->prepare("UPDATE orders SET status = 'revision_requested' WHERE id = ?")->execute([$order_id]);
            } else {
                $pdo->prepare("UPDATE orders SET status = 'awaiting_approval' WHERE id = ? AND status = 'disputed'")->execute([$order_id]);
            }
        }

        $pdo->commit();
        header("Location: ../admin/disputes.php?msg=resolved");
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: ../admin/disputes.php?msg=error");
        exit();
    }
}

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
        'disputed',
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
