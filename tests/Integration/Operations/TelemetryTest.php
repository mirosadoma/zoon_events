<?php

namespace Tests\Integration\Operations;

use App\Modules\Operations\Application\Telemetry\TelemetryPipeline;
use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;
use App\Modules\Shared\Support\Redaction\SecretRedactor;
use RuntimeException;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    public function test_sensitive_values_are_redacted_before_export(): void
    {
        $exporter = new class implements TelemetryExporter
        {
            public array $events = [];

            public function log(string $event, array $context): void
            {
                $this->events[] = compact('event', 'context');
            }

            public function metric(string $name, float $value, array $tags = []): void {}

            public function trace(string $name, array $context = []): void {}

            public function report(\Throwable $throwable, array $context = []): void {}
        };

        $pipeline = new TelemetryPipeline($exporter);
        $pipeline->emit('safe.test', [
            'correlation_id' => 'telemetry-safe-correlation',
            'password' => 'must-not-escape',
            'status' => 200,
        ]);

        self::assertCount(1, $exporter->events);
        self::assertSame('safe.test', $exporter->events[0]['event']);
        self::assertSame('telemetry-safe-correlation', $exporter->events[0]['context']['correlation_id']);
        self::assertSame(200, $exporter->events[0]['context']['status']);
        self::assertSame(SecretRedactor::REDACTED, $exporter->events[0]['context']['password']);
    }

    public function test_exporter_failure_never_fails_core_execution(): void
    {
        $pipeline = new TelemetryPipeline(new class implements TelemetryExporter
        {
            public function log(string $event, array $context): void
            {
                throw new RuntimeException('offline');
            }

            public function metric(string $name, float $value, array $tags = []): void {}

            public function trace(string $name, array $context = []): void {}

            public function report(\Throwable $throwable, array $context = []): void {}
        });

        $pipeline->emit('safe.test', ['password' => 'must-not-escape']);

        self::assertSame(1, $pipeline->failures());
    }
}
