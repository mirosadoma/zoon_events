<?php

namespace App\Modules\Tenancy\Domain\Events;

final readonly class TenantCreated
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $reason,
        public string $outcome = 'succeeded',
    ) {}
}
