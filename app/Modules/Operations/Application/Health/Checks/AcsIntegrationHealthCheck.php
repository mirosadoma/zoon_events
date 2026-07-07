<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\Operations\Application\Health\HealthCheckResult;
use App\Modules\Operations\Contracts\HealthCheck;

final readonly class AcsIntegrationHealthCheck implements HealthCheck
{
    public function __construct(private AcsAdapter $adapter) {}

    public function category(): string
    {
        return 'acs_integration';
    }

    public function run(): HealthCheckResult
    {
        $started = microtime(true);
        $result = $this->adapter->health();

        $status = match ($result->status) {
            'online', 'degraded' => 'ok',
            default => 'unavailable',
        };

        return new HealthCheckResult(
            $this->category(),
            $status,
            (int) round((microtime(true) - $started) * 1000),
            $result->reasonCode,
        );
    }
}
