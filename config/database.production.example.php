<?php
declare(strict_types=1);

// Copy this file to database.production.php on the backend server. The real
// file is intentionally ignored by Git so database credentials are not public.
return [
    'host' => 'YOUR_INFINITYFREE_MYSQL_HOST',
    'port' => 3306,
    'database' => 'YOUR_INFINITYFREE_DATABASE_NAME',
    'username' => 'YOUR_INFINITYFREE_MYSQL_USERNAME',
    'password' => 'YOUR_INFINITYFREE_MYSQL_PASSWORD',
    'charset' => 'utf8mb4',
];
