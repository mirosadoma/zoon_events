<?php

return [
    'public_live_path' => '/api/v1/health/live',
    'public_ready_path' => '/api/v1/health/ready',
    'checks' => [
        'database',
        'queue',
        'storage',
        'audit_key',
        'data_protection',
        'credential_signing',
        'payments',
        'notifications',
        'apple_wallet',
        'google_wallet',
        'acs_integration',
        'venue_marketplace',
        'config',
    ],
];
