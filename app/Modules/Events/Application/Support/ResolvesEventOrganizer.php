<?php

namespace App\Modules\Events\Application\Support;

use App\Exceptions\FoundationException;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class ResolvesEventOrganizer
{
    public function __construct(private PermissionEvaluator $permissions) {}

    public function resolve(TenantContext $context, ?int $organizerUserId = null): int
    {
        $requiresSelection = $this->permissions->hasPlatformPermission($context->actor, 'platform.tenant.manage');

        if ($organizerUserId !== null) {
            $this->assertActiveOrganizer($context->tenant->id, $organizerUserId);

            return $organizerUserId;
        }

        if ($requiresSelection) {
            throw FoundationException::validation('organizer_required', 'Select an organizer for this event.');
        }

        return (int) $context->actor->id;
    }

    private function assertActiveOrganizer(int|string $tenantId, int $userId): void
    {
        $isOrganizer = DB::table('tenant_memberships as memberships')
            ->join('tenant_role_assignments as assignments', function ($join): void {
                $join->on('assignments.tenant_membership_id', '=', 'memberships.id')
                    ->on('assignments.tenant_id', '=', 'memberships.tenant_id')
                    ->whereNull('assignments.revoked_at')
                    ->where(function ($query): void {
                        $query->whereNull('assignments.expires_at')
                            ->orWhere('assignments.expires_at', '>', now());
                    });
            })
            ->join('tenant_roles as roles', function ($join): void {
                $join->on('roles.id', '=', 'assignments.tenant_role_id')
                    ->on('roles.tenant_id', '=', 'assignments.tenant_id');
            })
            ->join('tenant_role_permissions as role_permissions', 'role_permissions.tenant_role_id', '=', 'roles.id')
            ->join('permissions', function ($join): void {
                $join->on('permissions.id', '=', 'role_permissions.permission_id')
                    ->where('permissions.key', 'event.manage')
                    ->where('permissions.scope', 'tenant');
            })
            ->where('memberships.tenant_id', $tenantId)
            ->where('memberships.user_id', $userId)
            ->where('memberships.status', 'active')
            ->exists();

        if (! $isOrganizer) {
            throw FoundationException::validation('organizer_invalid', 'The selected organizer must be an active tenant member with event management access.');
        }
    }

    /** @return list<array{id:string,name:string,email:string}> */
    public function candidates(int|string $tenantId): array
    {
        return DB::table('tenant_memberships as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->join('tenant_role_assignments as assignments', function ($join): void {
                $join->on('assignments.tenant_membership_id', '=', 'memberships.id')
                    ->on('assignments.tenant_id', '=', 'memberships.tenant_id')
                    ->whereNull('assignments.revoked_at')
                    ->where(function ($query): void {
                        $query->whereNull('assignments.expires_at')
                            ->orWhere('assignments.expires_at', '>', now());
                    });
            })
            ->join('tenant_roles as roles', function ($join): void {
                $join->on('roles.id', '=', 'assignments.tenant_role_id')
                    ->on('roles.tenant_id', '=', 'assignments.tenant_id');
            })
            ->join('tenant_role_permissions as role_permissions', 'role_permissions.tenant_role_id', '=', 'roles.id')
            ->join('permissions', function ($join): void {
                $join->on('permissions.id', '=', 'role_permissions.permission_id')
                    ->where('permissions.key', 'event.manage')
                    ->where('permissions.scope', 'tenant');
            })
            ->where('memberships.tenant_id', $tenantId)
            ->where('memberships.status', 'active')
            ->where('users.status', 'active')
            ->select(['users.id', 'users.name', 'users.email'])
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
            ])
            ->all();
    }

    public function requiresSelection(TenantContext $context): bool
    {
        return $this->permissions->hasPlatformPermission($context->actor, 'platform.tenant.manage');
    }
}
