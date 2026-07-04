<?php

return [
    'email_adapter' => env('NOTIFICATIONS_EMAIL_ADAPTER', 'log'),
    'sms_adapter' => env('NOTIFICATIONS_SMS_ADAPTER', 'disabled'),
    'allow_network' => (bool) env('NOTIFICATIONS_ALLOW_NETWORK', false),
    'timeout_ms' => (int) env('NOTIFICATIONS_TIMEOUT_MS', 5000),
    'unifonic' => [
        'api_url' => env('UNIFONIC_API_URL', 'https://el.cloud.unifonic.com/rest/SMS/messages'),
        'app_sid_reference' => env('UNIFONIC_APP_SID_REFERENCE'),
        'sender_id' => env('UNIFONIC_SENDER_ID'),
        'callback_route_token' => env('UNIFONIC_CALLBACK_ROUTE_TOKEN'),
    ],
];
