<?php

return [
    'catalog' => [
        'cache_enabled' => (bool) env('MARKETPLACE_CATALOG_CACHE_ENABLED', false),
        'cache_ttl_seconds' => (int) env('MARKETPLACE_CATALOG_CACHE_TTL_SECONDS', 300),
    ],

    'activation' => [
        'batch_size' => (int) env('MARKETPLACE_ACTIVATION_BATCH_SIZE', 100),
    ],

    'statement' => [
        'batch_size' => (int) env('MARKETPLACE_STATEMENT_BATCH_SIZE', 100),
    ],

    'export' => [
        'chunk_size' => (int) env('MARKETPLACE_EXPORT_CHUNK_SIZE', 500),
    ],

    'retention' => [
        'statement_days' => (int) env('MARKETPLACE_RETENTION_STATEMENT_DAYS', 2555),
        'dispute_days' => (int) env('MARKETPLACE_RETENTION_DISPUTE_DAYS', 2555),
        'audit_days' => (int) env('MARKETPLACE_RETENTION_AUDIT_DAYS', 2555),
    ],

    'observability' => [
        'catalog_queries_enabled' => (bool) env('MARKETPLACE_OBSERVABILITY_CATALOG_QUERIES_ENABLED', false),
        'provisioning_enabled' => (bool) env('MARKETPLACE_OBSERVABILITY_PROVISIONING_ENABLED', false),
        'lifecycle_commands_enabled' => (bool) env('MARKETPLACE_OBSERVABILITY_LIFECYCLE_COMMANDS_ENABLED', false),
    ],
];
