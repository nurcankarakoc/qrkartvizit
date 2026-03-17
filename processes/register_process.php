<?php
// processes/register_process.php
session_start();
require_once '../core/db.php';
require_once '../core/security.php';

function upload_logo_file(array $file, int $user_id): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo yüklenirken bir hata oluştu.');
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Logo dosyası en fazla 5MB olabilir.');
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $tmp_name = $file['tmp_name'] ?? '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp_name);
    if (!isset($allowed_mimes[$mime])) {
        throw new RuntimeException('Logo için sadece JPG, PNG veya WEBP kabul edilir.');
    }

    $uploads_dir = '../assets/uploads/logos/';
    if (!is_dir($uploads_dir) && !mkdir($uploads_dir, 0755, true) && !is_dir($uploads_dir)) {
        throw new RuntimeException('Logo klasörü oluşturulamadı.');
    }

    $file_name = 'logo_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_mimes[$mime];
    $logo_path = 'assets/uploads/logos/' . $file_name;

    if (!move_uploaded_file($tmp_name, '../' . $logo_path)) {
        throw new RuntimeException('Logo dosyası kaydedilemedi.');
    }

    return $logo_path;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_or_redirect('../auth/register.php?error=csrf');

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $package = $_POST['package'] ?? 'smart';
    $kvkk_approved = isset($_POST['kvkk_approved']) ? 1 : 0;
    $company_name = $_POST['company_name'] ?? '';
    $job_title = $_POST['job_title'] ?? '';
    $design_notes = $_POST['design_notes'] ?? '';

    // Temel kontroller
    if (empty($name) || empty($email) || empty($password)) {
        die("Lütfen zorunlu alanları doldurun.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Geçerli bir e-posta adresi girin.");
    }
    if (!$kvkk_approved) {
        die("Devam etmek için KVKK onayı gereklidir.");
    }

    $allowed_packages = ['classic', 'smart', 'panel'];
    if (!in_array($package, $allowed_packages, true)) {
        $package = 'smart';
    }

    try {
        $pdo->beginTransaction();

        // 1. Kullanıcıyı oluştur
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, kvkk_approved) VALUES (?, ?, ?, ?, 'customer', ?)");
        $stmt->execute([$name, $email, $hashed_password, $phone, $kvkk_approved]);
        $user_id = $pdo->lastInsertId();

        // 2. Logo yükleme işlemi
        $logo_path = upload_logo_file($_FILES['logo'] ?? [], (int) $user_id);

        // 3. Siparişi oluştur
        $revision_count = ($package === 'panel') ? 0 : 2;
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, package, company_name, job_title, logo_path, design_notes, revision_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $package, $company_name, $job_title, $logo_path, $design_notes, $revision_count]);

        // 4. Profil şablonunu oluştur (Sadece Panel ise anında aktifleşebilir)
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))) . '-' . rand(100, 999);
        $stmt = $pdo->prepare("INSERT INTO profiles (user_id, slug, full_name, title, company, phone_work, email_work) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $slug, $name, $job_title, $company_name, $phone, $email]);

        $pdo->commit();

        // Başarılı
        header("Location: ../auth/login.php?success=register");
        exit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() == 23000) {
            die("Bu e-posta adresi zaten kayıtlı.");
        }
        error_log($e->getMessage());
        die("Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.");
    }
}
