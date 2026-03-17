<?php
session_start();
require_once '../core/db.php';

// Designer check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    die("Yetkisiz erişim.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['draft_file'])) {
    $order_id = $_POST['order_id'] ?? null;
    $designer_id = $_SESSION['user_id'];

    if (!$order_id) {
        die("Sipariş ID bulunamadı.");
    }

    $file = $_FILES['draft_file'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_exts)) {
        die("Desteklenmeyen dosya formatı.");
    }

    if ($file['error'] === 0) {
        $upload_dir = '../assets/uploads/drafts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = 'draft_' . $order_id . '_' . time() . '.' . $file_ext;
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
                header("Location: ../designer/order_details.php?id=" . $order_id . "&success=uploaded");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                die("Veritabanı hatası: " . $e->getMessage());
            }
        } else {
            die("Dosya taşıma hatası.");
        }
    } else {
        die("Yükleme hatası: " . $file['error']);
    }
} else {
    header("Location: ../designer/dashboard.php");
    exit();
}
