<?php
require_once '../core/db.php';
require_once '../core/security.php';
require_once '../core/customer_access.php';

ensure_session_started();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

verify_csrf_or_redirect('../admin/packages.php?msg=invalid');

function admin_package_clean_lines(?string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', (string)$value) ?: [];
    $items = [];
    foreach ($lines as $line) {
        $trimmed = trim((string)$line);
        if ($trimmed !== '') {
            $items[] = $trimmed;
        }
    }

    return $items;
}

$slug = qrk_normalize_package_slug($_POST['slug'] ?? '');
if ($slug === '') {
    header('Location: ../admin/packages.php?msg=invalid');
    exit();
}

$defaults = qrk_get_package_definition($slug, $pdo);
$label = trim((string)($_POST['label'] ?? ''));
$short_label = trim((string)($_POST['short_label'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$register_title = trim((string)($_POST['register_title'] ?? ''));
$register_subtitle = trim((string)($_POST['register_subtitle'] ?? ''));
$register_badge = trim((string)($_POST['register_badge'] ?? ''));
$register_price_text = trim((string)($_POST['register_price_text'] ?? ''));
$register_note = trim((string)($_POST['register_note'] ?? ''));
$register_panel_text = trim((string)($_POST['register_panel_text'] ?? ''));
$price = max(0, (float)($_POST['price'] ?? $defaults['price']));
$included_revisions = max(0, (int)($_POST['included_revisions'] ?? $defaults['included_revisions']));
$has_digital_profile = isset($_POST['has_digital_profile']) ? 1 : 0;
$has_physical_print = isset($_POST['has_physical_print']) ? 1 : 0;
$is_active = isset($_POST['is_active']) ? 1 : 0;
$included_features = admin_package_clean_lines($_POST['included_features'] ?? '');
$excluded_features = admin_package_clean_lines($_POST['excluded_features'] ?? '');
$register_features = admin_package_clean_lines($_POST['register_features'] ?? '');

if ($label === '') {
    $label = (string)$defaults['label'];
}
if ($short_label === '') {
    $short_label = (string)$defaults['short_label'];
}
if ($description === '') {
    $description = (string)$defaults['description'];
}
if ($register_title === '') {
    $register_title = (string)$defaults['register_title'];
}
if ($register_subtitle === '') {
    $register_subtitle = (string)$defaults['register_subtitle'];
}
if ($register_price_text === '') {
    $register_price_text = (string)$defaults['register_price_text'];
}
if ($register_note === '') {
    $register_note = (string)$defaults['register_note'];
}
if ($register_panel_text === '') {
    $register_panel_text = (string)$defaults['register_panel_text'];
}
if ($included_features === []) {
    $included_features = (array)$defaults['included_features'];
}
if ($register_features === []) {
    $register_features = (array)$defaults['register_features'];
}

qrk_ensure_package_content_schema($pdo);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO packages (
            name, display_label, short_label, slug, price, has_physical_print, has_digital_profile, has_qr_code,
            included_revisions, description_text, included_features_json, excluded_features_json, register_title,
            register_subtitle, register_badge, register_price_text, register_features_json, register_note,
            register_panel_text, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            display_label = VALUES(display_label),
            short_label = VALUES(short_label),
            price = VALUES(price),
            has_physical_print = VALUES(has_physical_print),
            has_digital_profile = VALUES(has_digital_profile),
            has_qr_code = VALUES(has_qr_code),
            included_revisions = VALUES(included_revisions),
            description_text = VALUES(description_text),
            included_features_json = VALUES(included_features_json),
            excluded_features_json = VALUES(excluded_features_json),
            register_title = VALUES(register_title),
            register_subtitle = VALUES(register_subtitle),
            register_badge = VALUES(register_badge),
            register_price_text = VALUES(register_price_text),
            register_features_json = VALUES(register_features_json),
            register_note = VALUES(register_note),
            register_panel_text = VALUES(register_panel_text),
            is_active = VALUES(is_active)'
    );

    $stmt->execute([
        $label,
        $label,
        $short_label,
        $slug,
        $price,
        $has_physical_print,
        $has_digital_profile,
        $has_digital_profile,
        $included_revisions,
        $description,
        json_encode($included_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($excluded_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $register_title,
        $register_subtitle,
        $register_badge,
        $register_price_text,
        json_encode($register_features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $register_note,
        $register_panel_text,
        $is_active,
    ]);

    header('Location: ../admin/packages.php?msg=updated');
    exit();
} catch (Throwable $e) {
    header('Location: ../admin/packages.php?msg=error');
    exit();
}
