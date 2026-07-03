<?php

return [
    'bootstrap_admin_email' => env('FOUNDATION_BOOTSTRAP_ADMIN_EMAIL', 'platform.admin@admin.com'),
    'bootstrap_admin_password' => env('FOUNDATION_BOOTSTRAP_ADMIN_PASSWORD', 'admin1234'),
    'name' => env('ZONETEC_NAME', 'Zonetec'),
    'deployment_mode' => env('ZONETEC_DEPLOYMENT_MODE', 'saas'),
    'default_timezone' => env('APP_TIMEZONE', 'UTC'),
    'supported_locales' => ['en', 'ar'],
    'idempotency_hours' => (int) env('IDEMPOTENCY_REPLAY_HOURS', 24),
];
