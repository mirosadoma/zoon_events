<?php

namespace App\Modules\Operations\Infrastructure\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;

final class NullTelemetryExporter implements TelemetryExporter
{
    public function log(string $event, array $context): void {}

    public function metric(string $name, float $value, array $tags = []): void {}

    public function trace(string $name, array $context = []): void {}

    public function report(\Throwable $throwable, array $context = []): void {}
}
