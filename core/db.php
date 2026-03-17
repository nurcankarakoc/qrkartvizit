<?php
// core/db.php
// .env destekli veritabanı yapılandırması ve güvenli PDO bağlantısı

/**
 * Minimal .env parser (dependency-free).
 */
function load_env_file(string $env_path): void
{
    if (!is_readable($env_path)) {
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
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

        // Strip optional single/double quotes around values.
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function env_value(string $key, string $default = ''): string
{
    $from_getenv = getenv($key);
    if ($from_getenv !== false) {
        return (string) $from_getenv;
    }
    if (isset($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key])) {
        return (string) $_SERVER[$key];
    }
    return $default;
}

$project_root = dirname(__DIR__);
load_env_file($project_root . DIRECTORY_SEPARATOR . '.env');

$host = env_value('DB_HOST', 'localhost');
$port = env_value('DB_PORT', '3307');
$db = env_value('DB_NAME', 'qrkartvizit_db');
$user = env_value('DB_USER', 'root');
$pass = env_value('DB_PASS', '');
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

function connect_with_port_fallback(
    string $host,
    string $port,
    string $db,
    string $charset,
    string $user,
    string $pass,
    array $options
): PDO {
    $candidates = array_values(array_unique([$port, '3307', '3306']));
    $last_exception = null;

    foreach ($candidates as $candidate_port) {
        $dsn = "mysql:host={$host};port={$candidate_port};dbname={$db};charset={$charset}";
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            $last_exception = $e;
        }
    }

    throw $last_exception ?? new \PDOException('Unknown database connection error.');
}

try {
    $pdo = connect_with_port_fallback($host, $port, $db, $charset, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log($e->getMessage());
    exit('Veritabanı bağlantı hatası. Lütfen daha sonra tekrar deneyiniz.');
}
?>
