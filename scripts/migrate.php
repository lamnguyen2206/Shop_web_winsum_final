<?php

declare(strict_types=1);

/**
 * Apply sql/migrations/001_fix_order_cart_return.sql once.
 *
 * Usage: php scripts/migrate.php
 * Optional: WINSUM_DB_NAME=winsumwebfinal_test php scripts/migrate.php
 */

$root = dirname(__DIR__);
require_once $root . '/config/database.php';

$migrationId = '001_fix_order_cart_return';
$sqlFile = $root . '/sql/migrations/' . $migrationId . '.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Missing migration file: {$sqlFile}\n");
    exit(1);
}

$conn->query(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id VARCHAR(64) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$check = $conn->prepare('SELECT id FROM schema_migrations WHERE id = ? LIMIT 1');
if (!$check) {
    fwrite(STDERR, $conn->error . "\n");
    exit(1);
}
$check->bind_param('s', $migrationId);
$check->execute();
$already = $check->get_result()->fetch_assoc();
$check->close();

if ($already) {
    echo "Migration {$migrationId} already applied.\n";
    exit(0);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Cannot read {$sqlFile}\n");
    exit(1);
}

$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
$ignorable = [1050, 1060, 1061, 1062, 1091, 1826];

foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    try {
        if (!$conn->query($statement)) {
            throw new RuntimeException($conn->error, $conn->errno);
        }
    } catch (mysqli_sql_exception $e) {
        if (in_array($e->getCode(), $ignorable, true)) {
            echo "Skip ({$e->getCode()}): " . strtok($statement, "\n") . "\n";
            continue;
        }
        fwrite(STDERR, "Migration failed ({$e->getCode()}): {$e->getMessage()}\n{$statement}\n");
        exit(1);
    }
}

$mark = $conn->prepare('INSERT INTO schema_migrations (id) VALUES (?)');
if (!$mark) {
    fwrite(STDERR, $conn->error . "\n");
    exit(1);
}
$mark->bind_param('s', $migrationId);
$mark->execute();
$mark->close();

echo "Applied {$migrationId}.\n";
