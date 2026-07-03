<?php

namespace App\Modules\Operations\Infrastructure\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;
use Illuminate\Support\Facades\Log;

final class LocalTelemetryExporter implements TelemetryExporter
{
    public function log(string $event, array $context): void
    {
        Log::channel(config('observability.logging_channel'))->info($event, $context);
    }

    public function metric(string $name, float $value, array $tags = []): void
    {
        Log::channel(config('observability.logging_channel'))->debug('metric', compact('name', 'value', 'tags'));
    }

    public function trace(string $name, array $context = []): void
    {
        Log::channel(config('observability.logging_channel'))->debug('trace', compact('name', 'context'));
    }

    public function report(\Throwable $throwable, array $context = []): void
    {
        Log::channel(config('observability.logging_channel'))->error('reported_error', ['exception' => $throwable::class] + $context);
    }
}
