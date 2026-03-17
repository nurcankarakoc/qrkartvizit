<?php
// processes/register_process.php
session_start();
require_once '../core/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $package = $_POST['package'] ?? 'smart';
    $company_name = $_POST['company_name'] ?? '';
    $job_title = $_POST['job_title'] ?? '';
    $design_notes = $_POST['design_notes'] ?? '';

    // Temel kontroller
    if (empty($name) || empty($email) || empty($password)) {
        die("Lütfen zorunlu alanları doldurun.");
    }

    try {
        // 1. Kullanıcıyı oluştur
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->execute([$name, $email, $hashed_password, $phone]);
        $user_id = $pdo->lastInsertId();

        // 2. Logo yükleme işlemi (basit örnek)
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $uploads_dir = '../assets/uploads/logos/';
            if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0777, true);
            
            $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $file_name = $user_id . '_' . time() . '.' . $file_ext;
            $logo_path = 'assets/uploads/logos/' . $file_name;
            
            move_uploaded_file($_FILES['logo']['tmp_name'], '../' . $logo_path);
        }

        // 3. Siparişi oluştur
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, package, company_name, job_title, logo_path, design_notes, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $package, $company_name, $job_title, $logo_path, $design_notes]);

        // 4. Profil şablonunu oluştur (Sadece Panel ise anında aktifleşebilir)
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))) . '-' . rand(100, 999);
        $stmt = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name, title, company, phone_work, email_work) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $slug, $name, $job_title, $company_name, $phone, $email]);

        // Başarılı
        header("Location: ../auth/login.php?success=register");
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            die("Bu e-posta adresi zaten kayıtlı.");
        }
        die("Bir hata oluştu: " . $e->getMessage());
    }
}
