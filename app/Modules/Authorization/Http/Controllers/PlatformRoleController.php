<?php

namespace App\Modules\Authorization\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformRoleController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly CursorPaginator $paginator,
    ) {}

    public function index(Request $request)
    {
        $page = $this->paginator->paginate(PlatformRole::query()->with('permissions'), 'platform:roles', [], $request->string('cursor')->toString(), $request->integer('page_size', 50));

        return $this->success(
            collect($page->items)->map(fn (PlatformRole $role): array => $this->mapRole($role))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required_without:name_en', 'string', 'max:100', 'unique:platform_roles,name'],
            'name_en' => ['required_without:name', 'string', 'max:100', 'unique:platform_roles,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $role = DB::transaction(function () use ($validated, $actor): PlatformRole {
            $role = PlatformRole::query()->create([
                'name' => $validated['name'] ?? $validated['name_en'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'created_by_user_id' => $actor->id,
            ]);
            $this->audit->writePlatform('role.created', 'succeeded', $actor, targetType: 'platform_role', targetId: $role->id);

            return $role;
        });

        return $this->success($this->mapRole($role), 201);
    }

    public function update(Request $request, string $platform_role_id)
    {
        $role = PlatformRole::query()->with('permissions')->findOrFail($platform_role_id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_en' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array', 'max:100'],
            'permissions.*' => ['string', Rule::exists('permissions', 'key')->where('scope', 'platform')],
        ]);
        if ($role->is_system) {
            throw FoundationException::conflict('system_role_protected', 'System roles cannot be modified.');
        }

        if (isset($validated['name_en']) && ! isset($validated['name'])) {
            $validated['name'] = $validated['name_en'];
        }
        unset($validated['name_en']);

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($role, $validated, $actor): void {
            $role->fill(collect($validated)->except('permissions')->all())->save();

            if (! array_key_exists('permissions', $validated)) {
                $this->audit->writePlatform('role.updated', 'succeeded', $actor, targetType: 'platform_role', targetId: $role->id);

                return;
            }

            /** @var User $actor */
            $permissionIds = Permission::query()
                ->where('scope', 'platform')
                ->whereIn('key', $validated['permissions'])
                ->pluck('id')
                ->all();

            DB::table('platform_role_permissions')->where('platform_role_id', $role->id)->delete();
            foreach ($permissionIds as $permissionId) {
                DB::table('platform_role_permissions')->insert([
                    'platform_role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'granted_by_user_id' => $actor->id,
                    'created_at' => now(),
                ]);
            }
            $this->audit->writePlatform('role.updated', 'succeeded', $actor, targetType: 'platform_role', targetId: $role->id);
        });

        return $this->success($this->mapRole($role->refresh()->load('permissions')));
    }

    public function destroy(string $platform_role_id)
    {
        $role = PlatformRole::query()->findOrFail($platform_role_id);

        if ($role->is_system) {
            throw FoundationException::conflict('system_role_protected', 'System roles cannot be deleted.');
        }

        /** @var User $actor */
        $actor = request()->user();

        DB::transaction(function () use ($role, $actor): void {
            $activeAssignments = DB::table('platform_role_assignments')
                ->where('platform_role_id', $role->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if ($activeAssignments) {
                throw FoundationException::conflict('role_in_use', 'A role with active assignments cannot be deleted.');
            }

            DB::table('platform_role_permissions')->where('platform_role_id', $role->id)->delete();
            $role->delete();
            $this->audit->writePlatform('role.deleted', 'succeeded', $actor, targetType: 'platform_role', targetId: $role->id);
        });

        return $this->empty();
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_id' => ['required', 'exists:platform_roles,id'],
            'reason' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $assignmentId = DB::transaction(function () use ($validated, $actor): string {
            $assignmentId = DB::table('platform_role_assignments')->insertGetId([
                'user_id' => $validated['user_id'],
                'platform_role_id' => $validated['role_id'],
                'granted_by_user_id' => $actor->id,
                'expires_at' => $validated['expires_at'] ?? null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->writePlatform('role.assigned', 'succeeded', $actor, targetType: 'platform_role_assignment', targetId: $assignmentId, metadata: ['reason' => $validated['reason']]);

            return $assignmentId;
        });

        return $this->success([
            'id' => $assignmentId,
            'user_id' => $validated['user_id'],
            'role_id' => $validated['role_id'],
            'expires_at' => $validated['expires_at'] ?? null,
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    public function revoke(Request $request, string $assignmentId)
    {
        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($assignmentId, $actor): void {
            $assignment = DB::table('platform_role_assignments as assignments')
                ->join('platform_roles as roles', 'roles.id', '=', 'assignments.platform_role_id')
                ->where('assignments.id', $assignmentId)
                ->select('assignments.*', 'roles.name', 'roles.is_system')
                ->lockForUpdate()
                ->first();
            abort_unless($assignment, 404);
            if ($assignment->is_system && $assignment->name === 'Platform Administrator') {
                $otherAdministrator = DB::table('platform_role_assignments as assignments')
                    ->join('platform_roles as roles', 'roles.id', '=', 'assignments.platform_role_id')
                    ->join('users', 'users.id', '=', 'assignments.user_id')
                    ->where('roles.name', 'Platform Administrator')
                    ->where('roles.is_system', true)
                    ->where('assignments.id', '!=', $assignmentId)
                    ->where('users.status', 'active')
                    ->whereNull('assignments.revoked_at')
                    ->where(fn ($query) => $query->whereNull('assignments.expires_at')->orWhere('assignments.expires_at', '>', now()))
                    ->exists();
                if (! $otherAdministrator) {
                    throw FoundationException::conflict('last_platform_administrator', 'The final active platform administrator cannot be removed.');
                }
            }

            $affected = DB::table('platform_role_assignments')
                ->where('id', $assignmentId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $actor->id,
                    'updated_at' => now(),
                ]);
            abort_if($affected !== 1, 404);
            $this->audit->writePlatform('role.revoked', 'succeeded', $actor, targetType: 'platform_role_assignment', targetId: $assignmentId);
        });

        return $this->empty();
    }

    private function mapRole(PlatformRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('key')->values()->all(),
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }
}
