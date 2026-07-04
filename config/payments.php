<?php

return [
    'default' => env('PAYMENTS_DEFAULT_ADAPTER', 'disabled'),
    'allow_network' => (bool) env('PAYMENTS_ALLOW_NETWORK', false),
    'timeout_ms' => (int) env('PAYMENTS_TIMEOUT_MS', 5000),
    'moyasar' => [
        'api_url' => env('MOYASAR_API_URL', 'https://api.moyasar.com/v1'),
        'secret_reference' => env('MOYASAR_SECRET_REFERENCE'),
        'webhook_secret_reference' => env('MOYASAR_WEBHOOK_SECRET_REFERENCE'),
        'mode' => env('MOYASAR_MODE', 'test'),
    ],
];
