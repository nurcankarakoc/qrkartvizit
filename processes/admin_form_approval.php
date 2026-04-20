<?php
require_once __DIR__ . '/../core/security.php';
ensure_session_started();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/dynamic_form.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'admin' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../admin/form-approvals.php?msg=csrf');

$admin_id = (int) $_SESSION['user_id'];
df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

function redirect_form_approvals(string $query): void
{
    $target = '../admin/form-approvals.php';
    if ($query !== '') {
        $target .= '?' . ltrim($query, '?');
    }
    header('Location: ' . $target);
    exit();
}

$action = trim((string) ($_POST['action'] ?? ''));
$request_id = (int) ($_POST['request_id'] ?? 0);
$review_note = trim((string) ($_POST['review_note'] ?? ''));

if ($request_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    redirect_form_approvals('msg=invalid');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM form_change_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('request_not_found');
    }
    if ((string) ($request['status'] ?? '') !== 'pending') {
        throw new RuntimeException('request_not_pending');
    }

    if ($action === 'approve') {
        $request_type = (string) ($request['request_type'] ?? '');
        $payload_raw = (string) ($request['payload_json'] ?? '{}');
        $payload = json_decode($payload_raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException('payload_invalid');
        }

        if ($request_type === 'field_create') {
            df_create_field($pdo, $payload, (int) ($request['requested_by'] ?? 0));
        } elseif ($request_type === 'option_create') {
            $field_id = (int) ($payload['field_id'] ?? $request['field_id'] ?? 0);
            if ($field_id <= 0) {
                throw new RuntimeException('invalid_field_reference');
            }
            df_create_option($pdo, $field_id, $payload);
        } else {
            throw new RuntimeException('request_type_invalid');
        }

        $update_stmt = $pdo->prepare(
            "UPDATE form_change_requests
             SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
             WHERE id = ?"
        );
        $update_stmt->execute([$admin_id, $review_note !== '' ? $review_note : null, $request_id]);

        $pdo->commit();
        redirect_form_approvals('msg=approved');
    }

    $reject_stmt = $pdo->prepare(
        "UPDATE form_change_requests
         SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
         WHERE id = ?"
    );
    $reject_stmt->execute([$admin_id, $review_note !== '' ? $review_note : null, $request_id]);

    $pdo->commit();
    redirect_form_approvals('msg=rejected');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('admin_form_approval_error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    redirect_form_approvals('msg=error');
}
