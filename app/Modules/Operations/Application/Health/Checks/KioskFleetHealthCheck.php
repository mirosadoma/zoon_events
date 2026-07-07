<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Kiosk\Contracts\KioskFleetSummaryProvider;
use App\Modules\Operations\Application\Health\HealthCheckResult;
use App\Modules\Operations\Contracts\HealthCheck;

final readonly class KioskFleetHealthCheck implements HealthCheck
{
    public function __construct(
        private KioskFleetSummaryProvider $summaryProvider,
    ) {}

    public function category(): string
    {
        return 'kiosk_fleet';
    }

    public function run(): HealthCheckResult
    {
        $started = microtime(true);

        $this->summaryProvider->summarize();

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        return new HealthCheckResult(
            $this->category(),
            'ok',
            $elapsedMs,
        );
    }
}
