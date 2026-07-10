<?php

namespace App\Modules\Operations\Application\Telemetry;

use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;
use App\Modules\Shared\Support\Redaction\SafeMetadata;
use Throwable;

final class TelemetryPipeline
{
    private int $failures = 0;

    public function __construct(private readonly TelemetryExporter $exporter) {}

    public function emit(string $event, array $context = []): void
    {
        try {
            $this->exporter->log($event, SafeMetadata::from($context));
        } catch (Throwable) {
            $this->failures++;
        }
    }

    public function failures(): int
    {
        return $this->failures;
    }
}
