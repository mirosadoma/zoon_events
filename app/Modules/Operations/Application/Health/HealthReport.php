<?php

namespace App\Modules\Operations\Application\Health;

use Carbon\CarbonImmutable;

final readonly class HealthReport
{
    /**
     * @param  list<HealthCheckResult>  $checks
     */
    public function __construct(
        public string $status,
        public CarbonImmutable $checkedAt,
        public array $checks,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'checked_at' => $this->checkedAt->toIso8601String(),
            'checks' => array_map(
                static fn (HealthCheckResult $check): array => $check->toArray(),
                $this->checks,
            ),
        ];
    }
}
