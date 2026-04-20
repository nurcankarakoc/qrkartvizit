<?php
require_once '../core/security.php';
ensure_session_started();
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

if ($order_id <= 0 || !in_array($action, ['approve', 'revise'], true)) {
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
if ($status === 'disputed') {
    $status = 'revision_requested';
}
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

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ?");
        $stmt->execute([$order_id]);

        $can_update_draft_status = table_exists($pdo, 'design_drafts')
            && table_has_column($pdo, 'design_drafts', 'order_id')
            && table_has_column($pdo, 'design_drafts', 'status');

        if ($can_update_draft_status) {
            $stmt = $pdo->prepare(
                "UPDATE design_drafts
                 SET status = 'approved'
                 WHERE order_id = ?
                 ORDER BY created_at DESC
                 LIMIT 1"
            );
            $stmt->execute([$order_id]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: ../customer/design-tracking.php?error=approve_failed");
        exit();
    }

    header("Location: ../customer/design-tracking.php?success=approved");
    exit();
}

if ($action === 'revise') {
    $remaining_revisions = (int)($order['revision_count'] ?? 0);
    $notes = trim((string)($_POST['revision_notes'] ?? $_POST['revision_note'] ?? ''));

    if (!$has_draft) {
        header("Location: ../customer/design-tracking.php?error=no_draft");
        exit();
    }

    if (!in_array($status, ['designing', 'awaiting_approval', 'revision_requested'], true)) {
        header("Location: ../customer/design-tracking.php?error=invalid_transition");
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

header("Location: ../customer/design-tracking.php?error=invalid_request");
exit();
?>
