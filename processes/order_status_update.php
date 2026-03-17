<?php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../auth/login.php");
    exit();
}

verify_csrf_or_redirect('../customer/design-tracking.php?error=csrf');

$user_id = $_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($order_id <= 0 || !in_array($action, ['approve', 'revise', 'dispute'], true)) {
    header("Location: ../customer/design-tracking.php?error=invalid_request");
    exit();
}

// Siparişin bu kullanıcıya ait olup olmadığını kontrol et
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) { echo "Yetkisiz işlem."; exit(); }

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'approved' WHERE id = ?");
    $stmt->execute([$order_id]);
    header("Location: ../customer/design-tracking.php?success=approved");
} 
elseif ($action === 'revise' && $order['revision_count'] > 0) {
    $notes = $_POST['revision_notes'] ?? '';
    // Durumu 'revision_requested' olarak güncelliyoruz ve revize hakkını düşüyoruz
    $stmt = $pdo->prepare("UPDATE orders SET status = 'revision_requested', revision_count = revision_count - 1, design_notes = ? WHERE id = ?");
    $stmt->execute([$notes, $order_id]);
    header("Location: ../customer/design-tracking.php?success=revised");
} elseif ($action === 'dispute') {
    $reason = $_POST['dispute_reason'] ?? '';
    // Uyuşmazlık tablosuna ekle
    $stmt = $pdo->prepare("INSERT INTO disputes (order_id, user_id, reason) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $user_id, $reason]);
    header("Location: ../customer/design-tracking.php?success=disputed");
} else {
    header("Location: ../customer/design-tracking.php?error=no_revision");
}
exit();
?>
