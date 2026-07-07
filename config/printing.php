<?php

return [
    'default_printer_adapter' => env('PRINTER_ADAPTER', 'fake'),

    'kiosk' => [
        'session_secret_length' => 40,
        'session_ttl_hours' => (int) env('KIOSK_SESSION_TTL_HOURS', 168),
        'default_offline_threshold_seconds' => 120,
    ],

    'lookup' => [
        'confirmation_code_ttl_seconds' => 300,
        'max_matches' => 8,
    ],

    'notifications' => [
        'lookup_confirmation_channel' => 'email',
    ],
];
