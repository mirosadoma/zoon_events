<?php

namespace App\Modules\Tenancy\Application\Queries;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;

/**
 * Picks the organizer-facing admin for a tenant.
 *
 * Platform staff are often also seeded as tenant members for demos; prefer a
 * membership whose user has no active platform role assignment.
 */
final class ResolveTenantAdminUser
{
    public function handle(Tenant $tenant): ?User
    {
        $preferred = $this->activeMemberships($tenant)
            ->whereHas('user', function ($query): void {
                $query->whereDoesntHave('platformAssignments', function ($assignments): void {
                    $assignments
                        ->whereNull('revoked_at')
                        ->where(function ($active): void {
                            $active->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
            })
            ->with('user')
            ->orderBy('id')
            ->first();

        if ($preferred?->user instanceof User) {
            return $preferred->user;
        }

        $fallback = $this->activeMemberships($tenant)
            ->with('user')
            ->orderBy('id')
            ->first();

        return $fallback?->user instanceof User ? $fallback->user : null;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<TenantMembership> */
    private function activeMemberships(Tenant $tenant)
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', LifecycleStatus::Active->value);
    }
}
