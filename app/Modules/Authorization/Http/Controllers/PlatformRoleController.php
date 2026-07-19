<?php

namespace App\Modules\Authorization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformRoleController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function index(): JsonResponse
    {
        $roles = PlatformRole::query()->latest()->limit(100)->get();

        $data = $roles->map(fn (PlatformRole $role): array => $this->mapRole($role));

        return $this->success($data->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:platform_roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $role = DB::transaction(function () use ($validated, $actor): PlatformRole {
            $role = PlatformRole::query()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'is_system' => false,
                'created_by_user_id' => $actor->id,
            ]);

            $permissionIds = Permission::query()
                ->where('scope', 'platform')
                ->whereIn('key', $validated['permissions'])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('platform_role_permissions')->insert([
                    'platform_role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'granted_by_user_id' => $actor->id,
                    'created_at' => now(),
                ]);
            }

            return $role;
        });

        $this->audit->writePlatform(
            'platform_role.created',
            'succeeded',
            $actor,
            targetType: 'platform_role',
            targetId: $role->id,
            metadata: ['name' => $validated['name']],
        );

        return $this->success($this->mapRole($role), 201);
    }

    public function update(Request $request, string $roleId): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($roleId);

        abort_if($role->is_system, 403, 'System roles cannot be modified.');

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160', "unique:platform_roles,name,{$role->id}"],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        DB::transaction(function () use ($role, $validated, $actor): void {
            $role->update(array_filter([
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'] ?? null,
            ]));

            if (isset($validated['permissions'])) {
                DB::table('platform_role_permissions')->where('platform_role_id', $role->id)->delete();

                $permissionIds = Permission::query()
                    ->where('scope', 'platform')
                    ->whereIn('key', $validated['permissions'])
                    ->pluck('id');

                foreach ($permissionIds as $permissionId) {
                    DB::table('platform_role_permissions')->insert([
                        'platform_role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'granted_by_user_id' => $actor->id,
                        'created_at' => now(),
                    ]);
                }
            }
        });

        $this->audit->writePlatform(
            'platform_role.updated',
            'succeeded',
            $actor,
            targetType: 'platform_role',
            targetId: $role->id,
        );

        return $this->success($this->mapRole($role->refresh()));
    }

    public function destroy(Request $request, string $roleId): JsonResponse
    {
        $role = PlatformRole::query()->findOrFail($roleId);

        abort_if($role->is_system, 403, 'System roles cannot be deleted.');

        $assignmentCount = DB::table('platform_role_assignments')
            ->where('platform_role_id', $role->id)
            ->whereNull('revoked_at')
            ->count();

        abort_if($assignmentCount > 0, 422, 'Role is still assigned to users. Remove assignments first.');

        /** @var User $actor */
        $actor = $request->user();

        $this->audit->writePlatform(
            'platform_role.deleted',
            'succeeded',
            $actor,
            targetType: 'platform_role',
            targetId: $role->id,
            metadata: ['name' => $role->name],
        );

        DB::transaction(function () use ($role): void {
            DB::table('platform_role_permissions')->where('platform_role_id', $role->id)->delete();
            $role->delete();
        });

        return $this->success(null, 204);
    }

    private function mapRole(PlatformRole $role): array
    {
        $permissionKeys = DB::table('platform_role_permissions as prp')
            ->join('permissions as p', 'p.id', '=', 'prp.permission_id')
            ->where('prp.platform_role_id', $role->id)
            ->pluck('p.key')
            ->all();

        return [
            'id' => (string) $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'permissions' => $permissionKeys,
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }
}
