<?php

namespace App\Modules\Operations\Application\Health;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $category,
        public string $status,
        public int $durationMs,
        public ?string $reasonCode = null,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'reason_code' => $this->reasonCode,
        ];
    }
}
