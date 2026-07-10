<?php

namespace App\Modules\Tenancy\Domain\Events;

final readonly class TenantStatusChanged
{
    public function __construct(
        public string $tenantId,
        public string $actorId,
        public string $from,
        public string $to,
        public string $reason,
    ) {}
}
