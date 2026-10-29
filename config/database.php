<?php

/**
 * @return array<string, mixed>
 */
function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $local = __DIR__ . '/local.php';
    $example = __DIR__ . '/local.example.php';
    $loaded = is_file($local) ? require $local : require $example;
    if (!is_array($loaded)) {
        $loaded = [];
    }
    $config = $loaded;

    $overrideName = getenv('WINSUM_DB_NAME');
    if (is_string($overrideName) && $overrideName !== '') {
        $config['db']['name'] = $overrideName;
    }

    return $config;
}

$appDb = appConfig()['db'] ?? [];
$host = (string) ($appDb['host'] ?? 'localhost');
$db = (string) ($appDb['name'] ?? 'winsumwebfinal');
$user = (string) ($appDb['user'] ?? 'root');
$pass = (string) ($appDb['pass'] ?? '');
$charset = (string) ($appDb['charset'] ?? 'utf8mb4');

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('LỖI KẾT NỐI DATABASE: ' . $conn->connect_error);
}

$conn->set_charset($charset);
