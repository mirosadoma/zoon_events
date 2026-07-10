<?php

namespace App\Modules\Tenancy\Application\Boundaries;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final class TenantLogContext
{
    public function __construct(
        private readonly TenantContextStore $tenants,
        private readonly RequestContextStore $requests,
    ) {}

    /** @return array<string, string> */
    public function current(): array
    {
        $tenant = $this->tenants->current();
        $request = $this->requests->current();

        return array_filter([
            'tenant_id' => $tenant->tenant->id,
            'actor_id' => $tenant->actor->id,
            'correlation_id' => $request?->correlationId->value,
            'request_id' => $request?->requestId->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
