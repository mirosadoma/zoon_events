<?php

namespace App\Modules\AdminConsole\Application;

use App\Models\User;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;

final class MembershipVisibility
{
    public function __construct(
        private readonly PermissionEvaluator $permissions,
    ) {}

    /**
     * @return list<int>
     */
    public function visibleCreatorIds(TenantContext $context, User $actor): array
    {
        $creators = collect([(int) $actor->id]);

        $delegates = TenantMembership::query()
            ->with('user')
            ->where('tenant_id', $context->tenant->id)
            ->where('created_by_user_id', $actor->id)
            ->get();

        foreach ($delegates as $delegateMembership) {
            $delegateUser = $delegateMembership->user;

            if ($delegateUser === null) {
                continue;
            }

            $delegateContext = new TenantContext($context->tenant, $delegateMembership, $delegateUser);

            if ($this->permissions->hasTenantPermission($delegateContext, 'membership.manage')) {
                $creators->push((int) $delegateMembership->user_id);
            }
        }

        return $creators->unique()->values()->all();
    }

    /**
     * Memberships the actor invited and can assign custom roles to.
     *
     * @param  Builder<TenantMembership>  $query
     * @return Builder<TenantMembership>
     */
    public function scopeAssignableMemberships(Builder $query, TenantContext $context, User $actor): Builder
    {
        return $query
            ->where('tenant_id', $context->tenant->id)
            ->where('user_id', '!=', $actor->id)
            ->where('created_by_user_id', $actor->id)
            ->whereNotExists(function ($subquery) use ($context): void {
                $subquery->selectRaw('1')
                    ->from('tenant_role_assignments as assignments')
                    ->join('tenant_roles as roles', function ($join): void {
                        $join->on('roles.id', '=', 'assignments.tenant_role_id')
                            ->on('roles.tenant_id', '=', 'assignments.tenant_id');
                    })
                    ->whereColumn('assignments.tenant_membership_id', 'tenant_memberships.id')
                    ->where('assignments.tenant_id', $context->tenant->id)
                    ->whereNull('assignments.revoked_at')
                    ->where(function ($expiresQuery): void {
                        $expiresQuery
                            ->whereNull('assignments.expires_at')
                            ->orWhere('assignments.expires_at', '>', now());
                    })
                    ->where('roles.is_system', true);
            });
    }

    /**
     * @param  Builder<TenantMembership>  $query
     * @return Builder<TenantMembership>
     */
    public function scopeVisibleMemberships(Builder $query, TenantContext $context, User $actor): Builder
    {
        return $query
            ->where('tenant_id', $context->tenant->id)
            ->where('user_id', '!=', $actor->id)
            ->whereIn('created_by_user_id', $this->visibleCreatorIds($context, $actor));
    }
}
