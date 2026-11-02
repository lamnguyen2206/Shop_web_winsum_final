<?php

function appIsLocalHost(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Tài khoản Hostinger có dạng u123456789_winsum. File đó không dùng cho XAMPP.
 *
 * @param array<string, mixed> $config
 */
function appConfigIsHosting(array $config): bool
{
    $db = is_array($config['db'] ?? null) ? $config['db'] : [];
    $user = (string) ($db['user'] ?? '');
    $name = (string) ($db['name'] ?? '');

    return preg_match('/^u\d+_/', $user) === 1 || preg_match('/^u\d+_/', $name) === 1;
}

/**
 * @return array<string, mixed>
 */
function appReadConfigFile(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $loaded = require $path;

    return is_array($loaded) ? $loaded : null;
}

/**
 * @return array<string, mixed>
 */
function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $dir = __DIR__;
    $example = appReadConfigFile($dir . '/local.example.php') ?? [];
    $local = appReadConfigFile($dir . '/local.php');
    $dev = appReadConfigFile($dir . '/local.dev.php');

    if (appIsLocalHost()) {
        if ($dev !== null) {
            $loaded = $dev;
        } elseif ($local !== null && !appConfigIsHosting($local)) {
            $loaded = $local;
        } else {
            $loaded = $example;
        }
    } else {
        $loaded = $local ?? $example;
    }
    $config = $loaded;

    $overrideName = getenv('WINSUM_DB_NAME');
    if (is_string($overrideName) && $overrideName !== '') {
        $config['db']['name'] = $overrideName;
    }

    return $config;
}

function appDebug(): bool
{
    return (bool) (appConfig()['app']['debug'] ?? false);
}

$appDb = appConfig()['db'] ?? [];
$host = (string) ($appDb['host'] ?? 'localhost');
$db = (string) ($appDb['name'] ?? 'winsumwebfinal');
$user = (string) ($appDb['user'] ?? 'root');
$pass = (string) ($appDb['pass'] ?? '');
$charset = (string) ($appDb['charset'] ?? 'utf8mb4');

$connectError = null;
try {
    $conn = @new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        $connectError = (string) $conn->connect_error;
    }
} catch (mysqli_sql_exception $e) {
    $connectError = $e->getMessage();
}

if ($connectError !== null) {
    error_log('Winsum DB connect failed: ' . $connectError);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Không kết nối được database: ' . $connectError . "\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Winsum Home</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:48px">'
        . '<h1>Hệ thống đang bảo trì</h1><p>Không kết nối được cơ sở dữ liệu. Vui lòng thử lại sau.</p>';
    if (appDebug()) {
        echo '<pre style="text-align:left;display:inline-block">' . htmlspecialchars($connectError, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
    exit;
}

$conn->set_charset($charset);
