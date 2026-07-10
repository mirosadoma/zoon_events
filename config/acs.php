<?php

return [
    'default_acs_adapter' => env('ACS_ADAPTER', 'mock'),

    'integration' => [
        'secret_length' => 40,
        'credential_ttl_hours' => (int) env('ACS_INTEGRATION_TTL_HOURS', 168),
    ],

    'authorization' => [
        'latency_budget_ms' => (int) env('ACS_LATENCY_BUDGET_MS', 500),
    ],

    'lane' => [
        'offline_threshold_seconds' => 120,
    ],
];
