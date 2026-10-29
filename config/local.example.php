<?php

declare(strict_types=1);

/**
 * Copy this file to config/local.php and adjust for the machine.
 * config/local.php is not committed.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'winsumwebfinal',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'name' => 'admin',
        'phone' => '0901000000',
        'email' => 'admin@winsumhome.vn',
        'password' => 'admin123',
    ],
    'mail' => [
        'transport' => 'log',
        'from' => 'Winsum Home <noreply@winsumhome.vn>',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
    ],
];
