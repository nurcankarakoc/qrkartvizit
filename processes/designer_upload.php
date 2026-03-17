<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

// Designer check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    die("Yetkisiz erişim.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['draft_file'])) {
    verify_csrf_or_redirect('../designer/dashboard.php?error=csrf');

    $order_id = $_POST['order_id'] ?? null;
    $designer_id = $_SESSION['user_id'];

    if (!(int)$order_id) {
        header("Location: ../designer/dashboard.php?error=invalid_order");
        exit();
    }

    $file = $_FILES['draft_file'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    if (!in_array($file_ext, $allowed_exts)) {
        header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=file_type");
        exit();
    }

    if ($file['error'] === 0) {
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=file_size");
            exit();
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed_mimes[$mime])) {
            header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=file_mime");
            exit();
        }

        $upload_dir = '../assets/uploads/drafts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $new_filename = 'draft_' . (int)$order_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
        $file_path = 'assets/uploads/drafts/' . $new_filename;

        if (move_uploaded_file($file['tmp_name'], '../' . $file_path)) {
            try {
                $pdo->beginTransaction();

                // 1. Insert into design_drafts
                $stmt = $pdo->prepare("INSERT INTO design_drafts (order_id, designer_id, file_path, status) VALUES (?, ?, ?, 'pending')");
                $stmt->execute([$order_id, $designer_id, $file_path]);

                // 2. Update order status and set current draft path
                $pdo->prepare("UPDATE orders SET status = 'awaiting_approval', draft_path = ? WHERE id = ?")->execute([$file_path, $order_id]);

                $pdo->commit();

                // Redirect back with success
                header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&success=uploaded");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=db");
                exit();
            }
        } else {
            header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=move");
            exit();
        }
    } else {
        header("Location: ../designer/order_details.php?id=" . (int)$order_id . "&error=upload");
        exit();
    }
} else {
    header("Location: ../designer/dashboard.php");
    exit();
}
