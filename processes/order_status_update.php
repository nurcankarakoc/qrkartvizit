<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'customer' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header("Location: ../auth/login.php");
    exit();
}

verify_csrf_or_redirect('../customer/design-tracking.php?error=csrf');

$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

if ($order_id <= 0 || !in_array($action, ['approve', 'revise', 'dispute', 'buy_extra_revision'], true)) {
    header("Location: ../customer/design-tracking.php?error=invalid_request");
    exit();
}

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

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: ../customer/design-tracking.php?error=unauthorized");
    exit();
}

$has_draft = !empty($order['draft_path']);
$status = (string)($order['status'] ?? 'pending');
$is_panel_only_package = strtolower(trim((string)($order['package'] ?? ''))) === 'panel';

if ($is_panel_only_package) {
    header("Location: ../customer/design-tracking.php?error=invalid_transition");
    exit();
}

if ($action === 'approve') {
    if (!$has_draft) {
        header("Location: ../customer/design-tracking.php?error=no_draft");
        exit();
    }

    if (!in_array($status, ['awaiting_approval', 'revision_requested', 'designing'], true)) {
        header("Location: ../customer/design-tracking.php?error=invalid_transition");
        exit();
    }

    $stmt = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ?");
    $stmt->execute([$order_id]);
    header("Location: ../customer/design-tracking.php?success=approved");
    exit();
}

if ($action === 'revise') {
    $remaining_revisions = (int)($order['revision_count'] ?? 0);
    $notes = trim((string)($_POST['revision_notes'] ?? ''));

    if (!$has_draft) {
        header("Location: ../customer/design-tracking.php?error=no_draft");
        exit();
    }

    if ($remaining_revisions <= 0) {
        header("Location: ../customer/design-tracking.php?error=no_revision");
        exit();
    }

    if ($notes === '') {
        header("Location: ../customer/design-tracking.php?error=revision_note_required");
        exit();
    }

    $update_sql = "UPDATE orders
                   SET status = 'revision_requested',
                       revision_count = GREATEST(revision_count - 1, 0),
                       design_notes = ?";
    $update_params = [$notes];

    if (table_has_column($pdo, 'orders', 'current_revision_count')) {
        $update_sql .= ", current_revision_count = COALESCE(current_revision_count, 0) + 1";
    }

    $update_sql .= " WHERE id = ?";
    $update_params[] = $order_id;

    $stmt = $pdo->prepare($update_sql);
    $stmt->execute($update_params);
    header("Location: ../customer/design-tracking.php?success=revised");
    exit();
}

if ($action === 'dispute') {
    $reason = trim((string)($_POST['dispute_reason'] ?? ''));
    if ($reason === '') {
        header("Location: ../customer/design-tracking.php?error=dispute_reason_required");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO disputes (order_id, user_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$order_id, $user_id, $reason]);

        $stmt = $pdo->prepare("UPDATE orders SET status = 'disputed' WHERE id = ?");
        $stmt->execute([$order_id]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: ../customer/design-tracking.php?error=dispute_failed");
        exit();
    }

    header("Location: ../customer/design-tracking.php?success=disputed");
    exit();
}

if ($action === 'buy_extra_revision') {
    $package_quantity = (int)($_POST['extra_revision_qty'] ?? 1);
    if ($package_quantity < 1) {
        $package_quantity = 1;
    }
    if ($package_quantity > 20) {
        $package_quantity = 20;
    }

    $unit_price = 99.00;
    $total_amount = round($unit_price * $package_quantity, 2);

    try {
        $pdo->beginTransaction();

        $update_sql = "UPDATE orders SET revision_count = COALESCE(revision_count, 0) + ?";
        $update_params = [$package_quantity];

        if (table_has_column($pdo, 'orders', 'total_allowed_revisions')) {
            $update_sql .= ", total_allowed_revisions = COALESCE(total_allowed_revisions, 0) + ?";
            $update_params[] = $package_quantity;
        }

        $update_sql .= " WHERE id = ? AND user_id = ?";
        $update_params[] = $order_id;
        $update_params[] = $user_id;

        $stmt = $pdo->prepare($update_sql);
        $stmt->execute($update_params);

        if (table_exists($pdo, 'payments')) {
            $payment_columns = [];
            $payment_values = [];

            if (table_has_column($pdo, 'payments', 'user_id')) {
                $payment_columns[] = 'user_id';
                $payment_values[] = $user_id;
            }
            if (table_has_column($pdo, 'payments', 'order_id')) {
                $payment_columns[] = 'order_id';
                $payment_values[] = $order_id;
            }
            if (table_has_column($pdo, 'payments', 'transaction_id')) {
                $payment_columns[] = 'transaction_id';
                $payment_values[] = 'REV-' . date('YmdHis') . '-' . $order_id . '-' . random_int(1000, 9999);
            }
            if (table_has_column($pdo, 'payments', 'amount')) {
                $payment_columns[] = 'amount';
                $payment_values[] = $total_amount;
            }
            if (table_has_column($pdo, 'payments', 'currency')) {
                $payment_columns[] = 'currency';
                $payment_values[] = 'TRY';
            }
            if (table_has_column($pdo, 'payments', 'type')) {
                $payment_columns[] = 'type';
                $payment_values[] = 'extra_revision';
            }
            if (table_has_column($pdo, 'payments', 'payment_type')) {
                $payment_columns[] = 'payment_type';
                $payment_values[] = 'extra_revision';
            }
            if (table_has_column($pdo, 'payments', 'status')) {
                $payment_columns[] = 'status';
                $payment_values[] = 'success';
            }
            if (table_has_column($pdo, 'payments', 'payment_details')) {
                $payment_columns[] = 'payment_details';
                $payment_values[] = json_encode(
                    [
                        'source' => 'customer_panel',
                        'unit_price' => $unit_price,
                        'quantity' => $package_quantity,
                        'note' => 'extra_revision_purchase',
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }

            if (!empty($payment_columns)) {
                $payment_columns_sql = implode(', ', array_map(static fn(string $col): string => "`{$col}`", $payment_columns));
                $payment_placeholders = implode(', ', array_fill(0, count($payment_values), '?'));
                $stmt_payment = $pdo->prepare("INSERT INTO payments ({$payment_columns_sql}) VALUES ({$payment_placeholders})");
                $stmt_payment->execute($payment_values);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: ../customer/design-tracking.php?error=extra_revision_failed");
        exit();
    }

    header("Location: ../customer/design-tracking.php?success=extra_revision_purchased");
    exit();
}

header("Location: ../customer/design-tracking.php?error=invalid_request");
exit();
?>
