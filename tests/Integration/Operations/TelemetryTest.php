<?php

namespace Tests\Integration\Operations;

use App\Modules\Operations\Application\Telemetry\TelemetryPipeline;
use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;
use RuntimeException;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    public function test_http_context_is_propagated_and_sensitive_values_are_redacted(): void
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
        $this->app->instance(TelemetryExporter::class, $exporter);
        $this->app->forgetInstance(TelemetryPipeline::class);

        $this->withHeader('X-Correlation-ID', 'telemetry-safe-correlation')
            ->getJson('/api/v1/health/live')
            ->assertOk();

        self::assertCount(1, $exporter->events);
        self::assertSame('http.request.completed', $exporter->events[0]['event']);
        self::assertSame('telemetry-safe-correlation', $exporter->events[0]['context']['correlation_id']);
        self::assertSame(200, $exporter->events[0]['context']['status']);
        self::assertArrayNotHasKey('password', $exporter->events[0]['context']);
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
