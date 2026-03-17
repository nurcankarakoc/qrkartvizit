<?php
session_start();
require_once '../core/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_POST['full_name'] ?? '';
$title = $_POST['title'] ?? '';
$company = $_POST['company'] ?? '';
$phone_work = $_POST['phone_work'] ?? '';
$bio = $_POST['bio'] ?? '';

// Profil ID'sini bul
$stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile_id = $stmt->fetchColumn();

if (!$profile_id) { echo "Profil bulunamadı."; exit(); }

// Profil bilgilerini güncelle
$sql = "UPDATE profiles SET full_name = ?, title = ?, company = ?, phone_work = ?, bio = ?";
$params = [$full_name, $title, $company, $phone_work, $bio];

// Fotoğraf yükleme işlemi
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $upload_dir = '../assets/uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $photo_path = 'assets/uploads/profiles/' . $file_name;
        $sql .= ", photo_path = ?";
        $params[] = $photo_path;
    }
}

$sql .= " WHERE id = ?";
$params[] = $profile_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Sosyal linkleri güncelle (Önce siliyoruz, sonra yeniden ekliyoruz - en basit yöntem)
$stmt = $pdo->prepare("DELETE FROM social_links WHERE profile_id = ?");
$stmt->execute([$profile_id]);

if (isset($_POST['platforms']) && is_array($_POST['platforms'])) {
    $stmt = $pdo->prepare("INSERT INTO social_links (profile_id, platform, url) VALUES (?, ?, ?)");
    foreach ($_POST['platforms'] as $index => $platform) {
        $url = $_POST['urls'][$index] ?? '';
        if (!empty($url)) {
            $stmt->execute([$profile_id, $platform, $url]);
        }
    }
}

header("Location: ../customer/profile.php?success=1");
exit();
?>
