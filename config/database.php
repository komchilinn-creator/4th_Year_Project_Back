<?php
declare(strict_types=1);

$environment = require __DIR__ . '/environment.php';

if ($environment === 'production') {
    $productionConfig = __DIR__ . '/database.production.php';
    if (!is_file($productionConfig)) {
        throw new RuntimeException(
            'Production database configuration is missing. Copy database.production.example.php to database.production.php and add the server credentials.'
        );
    }

    return require $productionConfig;
}

return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'qr_attendance',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];
