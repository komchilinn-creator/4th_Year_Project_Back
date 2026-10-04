<?php
declare(strict_types=1);

$configuredEnvironment = strtolower(trim((string) getenv('APP_ENV')));
if (in_array($configuredEnvironment, ['development', 'production'], true)) {
    return $configuredEnvironment;
}

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);

return $host === 'easyqrapi.freedev.app' ? 'production' : 'development';

