<?php

namespace App\Modules\Tenancy\Application\Queue;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Contracts\Queue\TenantAwareJob;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Closure;

final class RestoreTenantContext
{
    public function __construct(private readonly TenantContextStore $store) {}

    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $serialized = $job->tenantJobContext();
        $tenant = Tenant::query()->find($serialized->tenantId);
        $membership = TenantMembership::query()->find($serialized->membershipId);
        $actor = User::query()->find($serialized->actorId);

        if (
            ! $tenant instanceof Tenant
            || ! $membership instanceof TenantMembership
            || ! $actor instanceof User
            || $tenant->status !== LifecycleStatus::Active
            || $membership->status !== LifecycleStatus::Active
            || ! $actor->isActive()
        ) {
            throw FoundationException::forbidden('tenant_context_invalid', 'The queued tenant context is no longer active.');
        }

        $this->store->bind($tenant, $membership, $actor);

        try {
            return $next($job);
        } finally {
            $this->store->clear();
        }
    }
}
