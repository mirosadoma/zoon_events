<?php

return [
    'logging_channel' => env('OBSERVABILITY_LOG_CHANNEL', 'stack'),
    'metrics_driver' => env('OBSERVABILITY_METRICS_DRIVER', 'null'),
    'tracing_driver' => env('OBSERVABILITY_TRACING_DRIVER', 'null'),
    'error_tracking_driver' => env('OBSERVABILITY_ERROR_TRACKING_DRIVER', 'null'),
];
