<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/webwinsum/index.php';

$views = [
    'home',
    'catalog',
    'cart',
    'checkout',
    'blog',
    'order-lookup',
    'admin-dashboard',
    'admin-products',
    'admin-orders',
    'admin-customers',
    'admin-returns',
    'admin-coupons',
    'admin-blog',
    'admin-reviews',
];

$failed = [];
foreach ($views as $view) {
    $_GET = ['view' => $view];
    ob_start();
    $code = 0;
    try {
        include 'index.php';
    } catch (Throwable $e) {
        $code = 1;
        $failed[] = "{$view}: " . $e->getMessage();
    }
    $output = ob_get_clean();
    if ($code !== 0 || $output === '') {
        $failed[] = "{$view}: empty or error output";
        continue;
    }
    if (stripos($output, 'fatal error') !== false || stripos($output, 'parse error') !== false) {
        $failed[] = "{$view}: PHP error in output";
        continue;
    }
    echo "OK: {$view}\n";
}

if ($failed !== []) {
    echo "\nFAILED:\n";
    foreach ($failed as $line) {
        echo "- {$line}\n";
    }
    exit(1);
}

echo "\nAll view smoke tests passed (" . count($views) . " views).\n";
