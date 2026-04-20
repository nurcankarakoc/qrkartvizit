<?php
// core/security.php
// Session + CSRF + Role helpers

function ensure_session_storage_ready(): void
{
    static $configured = false;

    if ($configured) {
        return;
    }

    $candidate_dirs = [];

    $local_app_data = getenv('LOCALAPPDATA');
    if (is_string($local_app_data) && $local_app_data !== '') {
        $candidate_dirs[] = rtrim($local_app_data, '/\\') . DIRECTORY_SEPARATOR . 'Temp' . DIRECTORY_SEPARATOR . 'qrkartvizit_sessions';
    }

    $temp_dir = sys_get_temp_dir();
    if (is_string($temp_dir) && $temp_dir !== '') {
        $candidate_dirs[] = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR . 'qrkartvizit_sessions';
    }

    $candidate_dirs[] = __DIR__ . '/../storage/sessions';

    foreach ($candidate_dirs as $session_dir) {
        if (!is_dir($session_dir)) {
            @mkdir($session_dir, 0775, true);
        }

        if (!is_dir($session_dir)) {
            continue;
        }

        $probe_file = rtrim($session_dir, '/\\') . DIRECTORY_SEPARATOR . '.session_probe';
        $probe_written = @file_put_contents($probe_file, 'ok');
        if ($probe_written === false) {
            continue;
        }

        @unlink($probe_file);
        @ini_set('session.save_path', $session_dir);
        session_save_path($session_dir);
        break;
    }

    $configured = true;
}

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ensure_session_storage_ready();
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_or_redirect(string $redirect_url): void
{
    ensure_session_started();
    $token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || !is_string($session_token) || !hash_equals($session_token, $token)) {
        header('Location: ' . $redirect_url);
        exit();
    }
}

/**
 * Kullanıcının session'ını VE veritabanını kontrol ederek rol doğrular.
 * Session'a tek başına güvenmek yeterli değil — DB yetkisi asıl kaynaktır.
 *
 * @param PDO    $pdo          Veritabanı bağlantısı
 * @param string $required_role 'customer' | 'designer' | 'admin'
 * @param string $redirect_url  Yetkisiz erişimde yönlendirilecek URL
 */
function require_role_or_redirect(PDO $pdo, string $required_role, string $redirect_url = '../auth/login.php'): void
{
    ensure_session_started();

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($user_id <= 0) {
        header('Location: ' . $redirect_url);
        exit();
    }

    // DB'den rol + aktiflik doğrula (session spoofing'e karşı)
    $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['is_active'] !== 1 || $row['role'] !== $required_role) {
        // Session'ı temizle ve yönlendir
        session_unset();
        session_destroy();
        header('Location: ' . $redirect_url);
        exit();
    }

    // Session'daki rolü DB ile senkronize tut
    $_SESSION['user_role'] = $row['role'];
}

