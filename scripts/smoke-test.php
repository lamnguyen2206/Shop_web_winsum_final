<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
$_SERVER['REQUEST_METHOD'] = 'GET';

$views = ['home', 'catalog', 'cart', 'admin-dashboard'];
foreach ($views as $view) {
    $_GET = ['view' => $view];
    if ($view === 'product') {
        $_GET['slug'] = 'den-chum-phong-khach';
    }
    $cmd = 'C:\\xampp\\php\\php.exe -d display_errors=1 index.php';
    passthru($cmd, $code);
    if ($code !== 0) {
        echo "FAIL: {$view} exit {$code}\n";
        exit(1);
    }
    echo "OK: {$view}\n";
}

echo "All smoke tests passed.\n";
