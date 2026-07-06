<?php

return [
    'default_apple_adapter' => env('WALLET_APPLE_ADAPTER', 'fake'),
    'default_google_adapter' => env('WALLET_GOOGLE_ADAPTER', 'fake'),
    'apple' => [
        'pass_type_identifier' => env('WALLET_APPLE_PASS_TYPE_IDENTIFIER'),
        'team_identifier' => env('WALLET_APPLE_TEAM_IDENTIFIER'),
        'certificate_secret_reference' => env('WALLET_APPLE_CERT_SECRET_REF'),
        'private_key_secret_reference' => env('WALLET_APPLE_KEY_SECRET_REF'),
        'web_service_base_url' => env('WALLET_APPLE_WEB_SERVICE_URL'),
    ],
    'google' => [
        'issuer_id' => env('WALLET_GOOGLE_ISSUER_ID'),
        'service_account_secret_reference' => env('WALLET_GOOGLE_SERVICE_ACCOUNT_SECRET_REF'),
    ],
    'single_entry_default_enabled' => (bool) env('WALLET_SINGLE_ENTRY_DEFAULT_ENABLED', true),
    'offline_allowlist_default_window_minutes' => (int) env('WALLET_OFFLINE_ALLOWLIST_WINDOW_MINUTES', 240),
];
