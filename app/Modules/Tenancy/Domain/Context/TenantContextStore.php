<?php

namespace App\Modules\Tenancy\Domain\Context;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Tenancy\Contracts\TenantContextResolver;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;

final class TenantContextStore implements TenantContextResolver
{
    private ?TenantContext $current = null;

    public function bind(Tenant $tenant, TenantMembership $membership, User $actor): TenantContext
    {
        if ($this->current !== null) {
            throw FoundationException::forbidden('tenant_context_rebind', 'Tenant context is already bound for this request.');
        }

        if ($membership->tenant_id !== $tenant->id) {
            throw FoundationException::forbidden('tenant_context_mismatch', 'Tenant membership does not belong to the resolved tenant.');
        }

        if ($membership->user_id !== $actor->id) {
            throw FoundationException::forbidden('tenant_context_mismatch', 'Tenant membership does not belong to the authenticated actor.');
        }

        $this->current = new TenantContext($tenant, $membership, $actor);

        return $this->current;
    }

    /** @deprecated Use bind() so mismatched objects are rejected. */
    public function set(TenantContext $context): void
    {
        $this->bind($context->tenant, $context->membership, $context->actor);
    }

    public function current(): TenantContext
    {
        if ($this->current === null) {
            throw FoundationException::forbidden('tenant_context_required', 'A trusted tenant context is required.');
        }

        return $this->current;
    }

    public function currentOrNull(): ?TenantContext
    {
        return $this->current;
    }

    public function clear(): void
    {
        $this->current = null;
    }
}
