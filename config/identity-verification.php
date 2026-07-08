<?php

return [
    'default_government_adapter' => env('IDENTITY_GOVERNMENT_ADAPTER', 'mock'),
    'default_face_adapter' => env('IDENTITY_FACE_ADAPTER', 'mock'),
    'residency' => env('IDENTITY_RESIDENCY', 'on_premise'),
    'cross_border_transfer' => (bool) env('IDENTITY_CROSS_BORDER_TRANSFER', false),

    'retention' => [
        'verification_days' => (int) env('IDENTITY_VERIFICATION_RETENTION_DAYS', 365),
        'biometric_days' => (int) env('IDENTITY_BIOMETRIC_RETENTION_DAYS', 30),
        'provider_payload_days' => (int) env('IDENTITY_PROVIDER_PAYLOAD_RETENTION_DAYS', 7),
    ],

    'consent_notice_version' => env('IDENTITY_CONSENT_NOTICE_VERSION', 'identity-v1'),

    'government_callback_secret' => env('IDENTITY_GOVERNMENT_CALLBACK_SECRET', 'identity-callback-test-secret'),
];
