<?php

return [
    'token_version' => 'zt1',
    'current_key_id' => env('CREDENTIAL_CURRENT_KEY_ID'),
    'key_ring' => json_decode((string) env('CREDENTIAL_KEY_RING', '{}'), true) ?: [],
    /*
     * Master switch for at-rest personal-data encryption.
     * Must remain true in production (enforced by ConfigurationValidator).
     */
    'personal_data_encryption_enabled' => filter_var(
        env('PERSONAL_DATA_ENCRYPTION_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),
    'personal_data_current_key_id' => env('PERSONAL_DATA_CURRENT_KEY_ID'),
    'personal_data_key_ring' => json_decode((string) env('PERSONAL_DATA_KEY_RING', '{}'), true) ?: [],
    'blind_index_current_key_id' => env('BLIND_INDEX_CURRENT_KEY_ID'),
    'blind_index_key_ring' => json_decode((string) env('BLIND_INDEX_KEY_RING', '{}'), true) ?: [],
];
