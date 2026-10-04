<?php
declare(strict_types=1);

$environment = require __DIR__ . '/environment.php';

return [
    'name' => 'AttendQR',
    'timezone' => 'Asia/Rangoon',
    'environment' => $environment,
    'cors_allowed_origins' => $environment === 'production'
        ? ['https://easyqrapi.freedev.app']
        : [],
];
