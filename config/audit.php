<?php

$configuredKeys = json_decode((string) env('AUDIT_KEY_RING', '{}'), true);

return [
    'driver' => env('AUDIT_DRIVER', 'database'),
    'hmac_algorithm' => env('AUDIT_HMAC_ALGORITHM', 'hmac-sha256-v1'),
    'current_key_id' => env('AUDIT_CURRENT_KEY_ID', 'local-dev'),
    'key_ring' => is_array($configuredKeys) ? $configuredKeys : [],
    'export_expiry_minutes' => (int) env('AUDIT_EXPORT_EXPIRY_MINUTES', 60),
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
    'max_search_days' => (int) env('AUDIT_MAX_SEARCH_DAYS', 31),
];
