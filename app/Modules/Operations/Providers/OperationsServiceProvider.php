<?php

namespace App\Modules\Operations\Providers;

use App\Modules\Operations\Application\Health\HealthService;
use App\Modules\Operations\Contracts\Telemetry\TelemetryExporter;
use App\Modules\Operations\Infrastructure\Telemetry\LocalTelemetryExporter;
use App\Modules\Operations\Infrastructure\Telemetry\NullTelemetryExporter;
use Illuminate\Support\ServiceProvider;

class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HealthService::class);
        $this->app->singleton(TelemetryExporter::class, fn () => config('observability.logging_channel') === 'null'
            ? new NullTelemetryExporter
            : new LocalTelemetryExporter);
    }
}
