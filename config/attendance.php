<?php

return [
    // Fixed West Yangon Technological University attendance area.
    'latitude' => 16.8695824,
    'longitude' => 96.0071808,
    'allowed_radius_meters' => 1609.344,

    // Browser readings worse than this are rejected even when their center point
    // appears to fall inside the allowed radius.
    'maximum_accuracy_meters' => 100.0,
];
