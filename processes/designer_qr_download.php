<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'designer') {
    header('Location: ../auth/login.php');
    exit();
}

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    http_response_code(400);
    exit('Geçersiz sipariş.');
}

function has_digital_profile_package(?string $package): bool
{
    if (!is_string($package) || $package === '') {
        return false;
    }
    $normalized = strtolower(trim($package));
    return str_contains($normalized, 'panel')
        || str_contains($normalized, 'smart')
        || str_contains($normalized, 'akilli');
}

function project_base_url(): string
{
    $env_app_url = trim((string)getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/processes/designer_qr_download.php');
    $project_path = preg_replace('#/processes/[^/]+$#', '', $script_name);
    return $scheme . '://' . $host . rtrim((string)$project_path, '/');
}

function build_qr_api_url(string $target_url): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=700x700&ecc=M&data=' . rawurlencode($target_url);
}

function stream_binary_download(string $binary, string $filename, string $mime = 'image/png'): void
{
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit();
}

function download_remote_binary(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'user_agent' => 'ZerosoftDesignerQR/1.0',
        ],
        'https' => [
            'timeout' => 8,
            'ignore_errors' => true,
            'user_agent' => 'ZerosoftDesignerQR/1.0',
        ],
    ]);

    $binary = @file_get_contents($url, false, $context);
    if (!is_string($binary) || $binary === '') {
        return null;
    }
    return $binary;
}

$stmt = $pdo->prepare("SELECT o.id, o.user_id, o.package, p.slug, p.qr_path
                       FROM orders o
                       LEFT JOIN profiles p ON p.user_id = o.user_id
                       WHERE o.id = ?
                       ORDER BY p.id DESC
                       LIMIT 1");
$stmt->execute([$order_id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Sipariş bulunamadı.');
}

if (!has_digital_profile_package((string)($row['package'] ?? ''))) {
    http_response_code(400);
    exit('Bu sipariş dijital profil paketi içermiyor.');
}

$profile_slug = trim((string)($row['slug'] ?? ''));
if ($profile_slug === '') {
    http_response_code(404);
    exit('Profil slug bulunamadı.');
}

$qr_path = trim((string)($row['qr_path'] ?? ''));
$public_profile_url = project_base_url() . '/kartvizit.php?slug=' . rawurlencode($profile_slug);
$qr_api_url = build_qr_api_url($public_profile_url);
$download_name = 'qr-order-' . $order_id . '.png';

if ($qr_path !== '' && !preg_match('#^https?://#i', $qr_path)) {
    $local_path = $qr_path;
    if (!preg_match('#^[A-Za-z]:\\\\#', $local_path) && !str_starts_with($local_path, DIRECTORY_SEPARATOR)) {
        $local_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $local_path);
    }

    if (is_file($local_path) && is_readable($local_path)) {
        $binary = (string)file_get_contents($local_path);
        if ($binary !== '') {
            $mime = 'image/png';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file($local_path);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }
            stream_binary_download($binary, $download_name, $mime);
        }
    }
}

if ($qr_path !== '' && preg_match('#^https?://#i', $qr_path)) {
    $binary = download_remote_binary($qr_path);
    if ($binary !== null) {
        stream_binary_download($binary, $download_name);
    }
}

$binary = download_remote_binary($qr_api_url);
if ($binary !== null) {
    stream_binary_download($binary, $download_name);
}

header('Location: ' . $qr_api_url);
exit();
?>
