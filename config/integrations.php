<?php

return [
    'default_adapter' => env('INTEGRATIONS_DEFAULT_ADAPTER', 'fake'),
    'allow_network' => env('INTEGRATIONS_ALLOW_NETWORK', false),
    'registered_adapters' => [
        'fake',
    ],
];
