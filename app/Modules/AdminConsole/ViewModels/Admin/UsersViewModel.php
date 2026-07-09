<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UsersViewModel
{
    /**
     * @param  Collection<int, TenantMembership>  $memberships
     * @return array{tenantId: string, users: list<array<string, mixed>>, roles: list<array<string, mixed>>}
     */
    public function index(string $tenantId, Collection $memberships, ?int $assignableRoleCreatorId = null): array
    {
        $roleRows = DB::table('tenant_role_assignments as assignments')
            ->join('tenant_roles as roles', function ($join): void {
                $join->on('roles.id', '=', 'assignments.tenant_role_id')
                    ->on('roles.tenant_id', '=', 'assignments.tenant_id');
            })
            ->where('assignments.tenant_id', $tenantId)
            ->whereNull('assignments.revoked_at')
            ->where(fn ($query) => $query->whereNull('assignments.expires_at')->orWhere('assignments.expires_at', '>', now()))
            ->select('assignments.tenant_membership_id', 'roles.id as role_id', 'roles.name as role_name')
            ->get()
            ->groupBy('tenant_membership_id');

        $rolesQuery = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->orderBy('name_en')
            ->orderBy('name');

        if ($assignableRoleCreatorId !== null) {
            $rolesQuery
                ->where('is_system', false)
                ->where('created_by_user_id', $assignableRoleCreatorId);
        }

        $roles = $rolesQuery
            ->get(['id', 'name', 'name_en', 'name_ar', 'is_system'])
            ->map(fn ($role): array => [
                'id' => (string) $role->id,
                'name' => (string) ($role->name_en ?? $role->name),
                'name_en' => (string) ($role->name_en ?? $role->name),
                'name_ar' => (string) ($role->name_ar ?? $role->name),
                'is_system' => (bool) $role->is_system,
            ])
            ->values()
            ->all();

        return [
            'tenantId' => $tenantId,
            'roles' => $roles,
            'users' => $memberships->map(function (TenantMembership $membership) use ($roleRows): array {
                $assigned = $roleRows->get($membership->id, collect());

                return [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'status' => $membership->status->value,
                    'user_status' => $membership->user->status->value,
                    'created_at' => $membership->created_at?->toIso8601String(),
                    'roles' => $assigned->map(fn ($row): array => [
                        'id' => (string) $row->role_id,
                        'name' => (string) $row->role_name,
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }
}
