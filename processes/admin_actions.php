<?php
session_start();
require_once '../core/db.php';

// Admin check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Yetkisiz erişim.");
}

$action = $_POST['action'] ?? '';

if ($action === 'add_designer') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'designer')");
        $stmt->execute([$name, $email, $password]);
        header("Location: ../admin/designers.php?msg=added");
        exit();
    } catch (Exception $e) {
        die("Hata: " . $e->getMessage());
    }
}

if ($action === 'resolve_dispute') {
    $dispute_id = $_POST['dispute_id'];
    $resolution = $_POST['resolution']; // favor_customer, favor_designer, closed
    
    try {
        $pdo->beginTransaction();

        // 1. Update dispute status
        $status = 'closed';
        if ($resolution === 'favor_customer') $status = 'resolved_favor_customer';
        if ($resolution === 'favor_designer') $status = 'resolved_favor_designer';

        $stmt = $pdo->prepare("UPDATE disputes SET status = ? WHERE id = ?");
        $stmt->execute([$status, $dispute_id]);

        // 2. Fetch order_id from dispute
        $stmt_info = $pdo->prepare("SELECT order_id FROM disputes WHERE id = ?");
        $stmt_info->execute([$dispute_id]);
        $order_id = $stmt_info->fetchColumn();

        // 3. If favor customer, increment revision_count
        if ($resolution === 'favor_customer') {
            $pdo->prepare("UPDATE orders SET revision_count = revision_count + 1, status = 'designing' WHERE id = ?")->execute([$order_id]);
        }

        $pdo->commit();
        header("Location: ../admin/disputes.php?msg=resolved");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Hata: " . $e->getMessage());
    }
}

header("Location: ../admin/dashboard.php");
exit();
