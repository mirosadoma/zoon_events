<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use Illuminate\Support\Collection;

final readonly class RolesViewModel
{
    /**
     * @param  Collection<int, TenantRole>  $roles
     * @return array{tenantId: string, roles: list<array<string, mixed>>}
     */
    public function index(string $tenantId, Collection $roles): array
    {
        return [
            'tenantId' => (string) $tenantId,
            'roles' => $roles->map(fn (TenantRole $role): array => [
                'id' => (string) $role->id,
                'name' => $role->name_en ?? $role->name,
                'name_en' => $role->name_en ?? $role->name,
                'name_ar' => $role->name_ar ?? $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->relationLoaded('permissions')
                    ? $role->permissions->pluck('key')->values()->all()
                    : [],
            ])->values()->all(),
        ];
    }
}
