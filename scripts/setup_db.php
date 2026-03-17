<?php
function load_env_simple(string $env_path): array
{
    if (!is_readable($env_path)) {
        return [];
    }

    $data = [];
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $data[$key] = $value;
    }

    return $data;
}

$env = load_env_simple(__DIR__ . '/../.env');

$host = $env['DB_HOST'] ?? 'localhost';
$port = $env['DB_PORT'] ?? '3307';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db_name = $env['DB_NAME'] ?? 'qrkartvizit_db';

function connect_server_with_fallback(string $host, string $port, string $user, string $pass): PDO
{
    $ports = array_values(array_unique([$port, '3307', '3306']));
    $last_exception = null;

    foreach ($ports as $p) {
        try {
            return new PDO("mysql:host={$host};port={$p}", $user, $pass);
        } catch (PDOException $e) {
            $last_exception = $e;
        }
    }

    throw $last_exception ?? new PDOException('Database server connection failed.');
}

try {
    $pdo = connect_server_with_fallback($host, $port, $user, $pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "Veritabanı oluşturuldu veya zaten var.\n";
    
    $pdo->exec("USE `{$db_name}`;");
    $sql = file_get_contents(__DIR__ . '/../database/database.sql');
    $pdo->exec($sql);
    echo "Tablolar başarıyla oluşturuldu.\n";
} catch (PDOException $e) {
    die("HATA: " . $e->getMessage());
}
?>
