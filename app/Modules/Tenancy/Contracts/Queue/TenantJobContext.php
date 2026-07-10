<?php

namespace App\Modules\Tenancy\Contracts\Queue;

use App\Modules\Shared\Domain\Context\RequestContextStore;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;

final readonly class TenantJobContext
{
    public function __construct(
        public string $tenantId,
        public string $membershipId,
        public string $actorId,
        public ?string $correlationId = null,
    ) {}

    public static function capture(TenantContextStore $tenants, RequestContextStore $requests): self
    {
        $context = $tenants->current();

        return new self(
            $context->tenant->id,
            $context->membership->id,
            $context->actor->id,
            $requests->current()?->correlationId->value,
        );
    }
}
