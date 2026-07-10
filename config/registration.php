<?php

return [
    'public_base_host' => env('REGISTRATION_PUBLIC_BASE_HOST', 'localhost'),
    'hold_minutes' => (int) env('REGISTRATION_HOLD_MINUTES', 15),
    'max_form_fields' => (int) env('REGISTRATION_MAX_FORM_FIELDS', 100),
    'max_event_attendees' => (int) env('REGISTRATION_MAX_EVENT_ATTENDEES', 100000),
    'allowed_locales' => ['en', 'ar'],
    'default_currency' => env('REGISTRATION_DEFAULT_CURRENCY', 'SAR'),
];
