<?php

$environment = require __DIR__ . '/environment.php';

return [
    // The bypass is available only in the local development profile. Production
    // always requires the browser's real GPS values.
    'allow_local_development_bypass' => $environment === 'development',
    // Change these values to the GPS point where attendance is allowed.
    'latitude' => 16.8409,
    'longitude' => 96.1735,
    'allowed_radius_meters' => 100.0,

    // Browser readings worse than this are rejected even when their center point
    // appears to fall inside the allowed radius.
    'maximum_accuracy_meters' => 100.0,
];
