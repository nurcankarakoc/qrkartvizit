<?php
require_once __DIR__ . '/../core/security.php';
ensure_session_started();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/dynamic_form.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'designer' ||
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../designer/form-control.php?msg=csrf');

$designer_id = (int) $_SESSION['user_id'];
df_ensure_dynamic_form_schema($pdo);
df_seed_default_form_fields($pdo);
df_seed_print_brief_fields($pdo);

function redirect_form_control(string $query): void
{
    $target = '../designer/form-control.php';
    if ($query !== '') {
        $target .= '?' . ltrim($query, '?');
    }
    header('Location: ' . $target);
    exit();
}

function insert_change_request(PDO $pdo, string $request_type, ?int $field_id, array $payload, int $requested_by): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO form_change_requests (request_type, field_id, payload_json, requested_by, status)
         VALUES (?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $request_type,
        $field_id,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $requested_by,
    ]);
}

$action = trim((string) ($_POST['action'] ?? ''));

try {
    if ($action === 'toggle_field') {
        $field_id = (int) ($_POST['field_id'] ?? 0);
        $is_active = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

        if ($field_id <= 0) {
            redirect_form_control('msg=invalid');
        }

        $stmt = $pdo->prepare('UPDATE form_fields SET is_active = ?, updated_by = ? WHERE id = ?');
        $stmt->execute([$is_active, $designer_id, $field_id]);
        redirect_form_control('msg=field_updated');
    }

    if ($action === 'toggle_option') {
        $option_id = (int) ($_POST['option_id'] ?? 0);
        $is_active = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

        if ($option_id <= 0) {
            redirect_form_control('msg=invalid');
        }

        $stmt = $pdo->prepare('UPDATE form_field_options SET is_active = ? WHERE id = ?');
        $stmt->execute([$is_active, $option_id]);
        redirect_form_control('msg=option_updated');
    }

    if ($action === 'request_new_field') {
        $field_label = trim((string) ($_POST['field_label'] ?? ''));
        $field_key = df_normalize_field_key((string) ($_POST['field_key'] ?? ''));
        if ($field_key === '' && $field_label !== '') {
            $field_key = df_normalize_field_key($field_label);
        }
        $field_type = strtolower(trim((string) ($_POST['field_type'] ?? 'text')));
        $placeholder = trim((string) ($_POST['placeholder'] ?? ''));
        $help_text = trim((string) ($_POST['help_text'] ?? ''));
        $default_value = trim((string) ($_POST['default_value'] ?? ''));
        $show_on_packages = trim((string) ($_POST['show_on_packages'] ?? ''));
        $required_on_packages = trim((string) ($_POST['required_on_packages'] ?? ''));

        $allowed_types = ['text', 'textarea', 'select', 'email', 'url', 'tel', 'number'];
        if ($field_key === '' || $field_label === '' || !in_array($field_type, $allowed_types, true)) {
            redirect_form_control('msg=invalid');
        }

        $payload = [
            'field_key' => $field_key,
            'field_label' => $field_label,
            'field_type' => $field_type,
            'placeholder' => $placeholder,
            'help_text' => $help_text,
            'default_value' => $default_value,
            'show_on_packages' => $show_on_packages,
            'required_on_packages' => $required_on_packages,
            'is_required' => (int) (!empty($_POST['is_required']) ? 1 : 0),
            'is_active' => 1,
            'sort_order' => 900,
        ];

        insert_change_request($pdo, 'field_create', null, $payload, $designer_id);
        redirect_form_control('msg=request_sent');
    }

    if ($action === 'request_new_option') {
        $field_id = (int) ($_POST['field_id'] ?? 0);
        $option_label = trim((string) ($_POST['option_label'] ?? ''));
        $option_value = trim((string) ($_POST['option_value'] ?? ''));

        if ($field_id <= 0 || $option_label === '') {
            redirect_form_control('msg=invalid');
        }

        $field_stmt = $pdo->prepare("SELECT id, field_type FROM form_fields WHERE id = ? LIMIT 1");
        $field_stmt->execute([$field_id]);
        $field_row = $field_stmt->fetch();
        if (!$field_row || (string) ($field_row['field_type'] ?? '') !== 'select') {
            redirect_form_control('msg=invalid');
        }

        $payload = [
            'field_id' => $field_id,
            'option_label' => $option_label,
            'option_value' => $option_value,
            'sort_order' => 900,
            'is_active' => 1,
        ];

        insert_change_request($pdo, 'option_create', $field_id, $payload, $designer_id);
        redirect_form_control('msg=request_sent');
    }

    redirect_form_control('msg=invalid');
} catch (Throwable $e) {
    error_log('designer_form_control_error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    redirect_form_control('msg=error');
}
