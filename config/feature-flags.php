<?php

return [
    'cache_enabled' => false,
    'default_status' => env('FEATURE_FLAGS_DEFAULT_STATUS', 'draft'),
    'non_flaggable' => [
        'tenant_isolation',
        'authentication',
        'authorization',
        'audit_integrity',
        'secret_protection',
        'data_residency',
    ],
];
