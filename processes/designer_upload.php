<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    die("Yetkisiz erisim.");
}

function redirect_order_error(int $order_id, string $error): void
{
    header("Location: ../designer/order_details.php?id=" . $order_id . "&error=" . urlencode($error));
    exit();
}

function upload_error_code_to_key(int $code): string
{
    $map = [
        UPLOAD_ERR_INI_SIZE => 'upload_ini_size',
        UPLOAD_ERR_FORM_SIZE => 'upload_form_size',
        UPLOAD_ERR_PARTIAL => 'upload_partial',
        UPLOAD_ERR_NO_FILE => 'upload_no_file',
        UPLOAD_ERR_NO_TMP_DIR => 'upload_tmp_dir',
        UPLOAD_ERR_CANT_WRITE => 'upload_cant_write',
        UPLOAD_ERR_EXTENSION => 'upload_extension',
    ];

    return $map[$code] ?? 'upload_unknown';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../designer/dashboard.php");
    exit();
}

verify_csrf_or_redirect('../designer/dashboard.php?error=csrf');

$order_id = (int)($_POST['order_id'] ?? 0);
$designer_id = (int)($_SESSION['user_id'] ?? 0);

if ($order_id <= 0 || $designer_id <= 0) {
    header("Location: ../designer/dashboard.php?error=invalid_order");
    exit();
}

if (!isset($_FILES['draft_file'])) {
    redirect_order_error($order_id, 'upload_no_file');
}

$file = $_FILES['draft_file'];
$php_upload_error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($php_upload_error !== UPLOAD_ERR_OK) {
    redirect_order_error($order_id, upload_error_code_to_key($php_upload_error));
}

if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
    redirect_order_error($order_id, 'file_size');
}

$allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$allowed_mimes = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    'application/x-pdf' => 'pdf',
];

$original_name = (string)($file['name'] ?? '');
$file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_exts, true)) {
    redirect_order_error($order_id, 'file_type');
}

$tmp_name = (string)($file['tmp_name'] ?? '');
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp_name);

if (!isset($allowed_mimes[$mime])) {
    // Fallback: if extension is valid but MIME is generic/unknown, use extension safely.
    if (in_array($file_ext, $allowed_exts, true)) {
        $safe_ext = $file_ext;
    } else {
        redirect_order_error($order_id, 'file_mime');
    }
} else {
    $safe_ext = $allowed_mimes[$mime];
}

$upload_dir = '../assets/uploads/drafts/';
if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
    redirect_order_error($order_id, 'upload_dir');
}

$new_filename = 'draft_' . $order_id . '_' . bin2hex(random_bytes(8)) . '.' . $safe_ext;
$file_path = 'assets/uploads/drafts/' . $new_filename;

if (!move_uploaded_file($tmp_name, '../' . $file_path)) {
    redirect_order_error($order_id, 'move');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO design_drafts (order_id, designer_id, file_path, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$order_id, $designer_id, $file_path]);

    $stmt = $pdo->prepare("UPDATE orders SET status = 'awaiting_approval', draft_path = ? WHERE id = ?");
    $stmt->execute([$file_path, $order_id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    redirect_order_error($order_id, 'db');
}

header("Location: ../designer/order_details.php?id=" . $order_id . "&success=uploaded");
exit();
?>
