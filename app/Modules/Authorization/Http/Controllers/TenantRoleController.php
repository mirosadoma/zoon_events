<?php

namespace App\Modules\Authorization\Http\Controllers;

use App\Exceptions\FoundationException;
use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use App\Modules\Tenancy\Infrastructure\Persistence\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantRoleController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contextStore,
        private readonly AuditWriter $audit,
        private readonly CursorPaginator $paginator,
    ) {}

    public function index(Request $request)
    {
        $context = $this->contextStore->current();
        $page = $this->paginator->paginate(
            TenantRole::query()->with('permissions')->where('tenant_id', $context->tenant->id),
            "tenant:{$context->tenant->id}:roles",
            [],
            $request->string('cursor')->toString(),
            $request->integer('page_size', 50),
        );

        return $this->success(
            collect($page->items)->map(fn (TenantRole $role): array => $this->mapRole($role))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function show(string $role_id)
    {
        $context = $this->contextStore->current();
        $role = $this->findTenantRole($context->tenant->id, $role_id)->load('permissions');

        return $this->success($this->mapRole($role));
    }

    public function store(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $role = DB::transaction(function () use ($context, $validated): TenantRole {
            $role = TenantRole::query()->create([
                'tenant_id' => $context->tenant->id,
                'name' => $validated['name_en'],
                'name_en' => $validated['name_en'],
                'name_ar' => $validated['name_ar'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'created_by_user_id' => $context->actor->id,
            ]);
            $this->audit->writeTenant('role.created', 'succeeded', $context, targetType: 'tenant_role', targetId: $role->id);

            return $role;
        });

        return $this->success($this->mapRole($role), 201);
    }

    public function update(Request $request, string $role_id)
    {
        $context = $this->contextStore->current();
        $role = $this->findTenantRole($context->tenant->id, $role_id)->load('permissions');
        $validated = $request->validate([
            'name_en' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($role->is_system) {
            abort(409, 'System roles cannot be modified.');
        }

        if (isset($validated['name_en'])) {
            $validated['name'] = $validated['name_en'];
        }

        DB::transaction(function () use ($role, $validated, $context): void {
            $role->fill($validated)->save();
            $this->audit->writeTenant('role.updated', 'succeeded', $context, targetType: 'tenant_role', targetId: $role->id);
        });

        return $this->success($this->mapRole($role->refresh()->load('permissions')));
    }

    public function replacePermissions(Request $request, string $role_id)
    {
        $context = $this->contextStore->current();
        $role = $this->findTenantRole($context->tenant->id, $role_id);
        $validated = $request->validate([
            'permissions' => ['required', 'array', 'max:100'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        $permissionIds = Permission::query()
            ->where('scope', 'tenant')
            ->whereIn('key', $validated['permissions'])
            ->pluck('id')
            ->all();

        if ($role->is_system) {
            abort(409, 'System roles cannot be modified.');
        }

        DB::transaction(function () use ($role, $permissionIds, $context): void {
            DB::table('tenant_role_permissions')->where('tenant_role_id', $role->id)->delete();
            foreach ($permissionIds as $permissionId) {
                DB::table('tenant_role_permissions')->insert([
                    'tenant_id' => $context->tenant->id,
                    'tenant_role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'granted_by_user_id' => $context->actor->id,
                    'created_at' => now(),
                ]);
            }
            $this->audit->writeTenant('role.permission_changed', 'succeeded', $context, targetType: 'tenant_role', targetId: $role->id);
        });

        return $this->success($this->mapRole($role->refresh()->load('permissions')));
    }

    public function assign(Request $request)
    {
        $context = $this->contextStore->current();
        $validated = $request->validate([
            'membership_id' => ['required', 'string'],
            'role_id' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        TenantMembership::queryForCurrentTenant()->findOrFail($validated['membership_id']);
        TenantRole::query()->where('tenant_id', $context->tenant->id)->findOrFail($validated['role_id']);

        $assignmentId = DB::transaction(function () use ($validated, $context): string {
            $assignmentId = DB::table('tenant_role_assignments')->insertGetId([
                'tenant_id' => $context->tenant->id,
                'tenant_membership_id' => $validated['membership_id'],
                'tenant_role_id' => $validated['role_id'],
                'granted_by_user_id' => $context->actor->id,
                'expires_at' => $validated['expires_at'] ?? null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit->writeTenant('role.assigned', 'succeeded', $context, targetType: 'tenant_role_assignment', targetId: $assignmentId);

            return $assignmentId;
        });

        return $this->success([
            'id' => $assignmentId,
            'tenant_id' => $context->tenant->id,
            'membership_id' => $validated['membership_id'],
            'role_id' => $validated['role_id'],
            'expires_at' => $validated['expires_at'] ?? null,
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    public function revoke(string $assignment_id)
    {
        $context = $this->contextStore->current();
        DB::transaction(function () use ($assignment_id, $context): void {
            $assignment = DB::table('tenant_role_assignments as assignments')
                ->join('tenant_roles as roles', function ($join): void {
                    $join->on('roles.id', '=', 'assignments.tenant_role_id')
                        ->on('roles.tenant_id', '=', 'assignments.tenant_id');
                })
                ->where('assignments.id', $assignment_id)
                ->where('assignments.tenant_id', $context->tenant->id)
                ->select('assignments.*', 'roles.name', 'roles.is_system')
                ->lockForUpdate()
                ->first();
            abort_unless($assignment, 404);
            if ($assignment->is_system && $assignment->name === 'Tenant Administrator') {
                $otherAdministrator = DB::table('tenant_role_assignments as assignments')
                    ->join('tenant_roles as roles', function ($join): void {
                        $join->on('roles.id', '=', 'assignments.tenant_role_id')->on('roles.tenant_id', '=', 'assignments.tenant_id');
                    })
                    ->join('tenant_memberships as memberships', function ($join): void {
                        $join->on('memberships.id', '=', 'assignments.tenant_membership_id')->on('memberships.tenant_id', '=', 'assignments.tenant_id');
                    })
                    ->where('assignments.tenant_id', $context->tenant->id)
                    ->where('assignments.id', '!=', $assignment_id)
                    ->where('roles.name', 'Tenant Administrator')
                    ->where('roles.is_system', true)
                    ->where('memberships.status', 'active')
                    ->whereNull('assignments.revoked_at')
                    ->where(fn ($query) => $query->whereNull('assignments.expires_at')->orWhere('assignments.expires_at', '>', now()))
                    ->exists();
                if (! $otherAdministrator) {
                    throw FoundationException::conflict('last_tenant_administrator', 'The final active tenant administrator cannot be removed.');
                }
            }

            $affected = DB::table('tenant_role_assignments')
                ->where('id', $assignment_id)
                ->where('tenant_id', $context->tenant->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $context->actor->id,
                    'updated_at' => now(),
                ]);

            abort_if($affected !== 1, 404);
            $this->audit->writeTenant('role.revoked', 'succeeded', $context, targetType: 'tenant_role_assignment', targetId: $assignment_id);
        });

        return $this->empty();
    }

    public function destroy(string $role_id)
    {
        $context = $this->contextStore->current();
        $role = $this->findTenantRole($context->tenant->id, $role_id);

        if ($role->is_system) {
            throw FoundationException::conflict('system_role_protected', 'System roles cannot be deleted.');
        }

        DB::transaction(function () use ($role, $context): void {
            $activeAssignments = DB::table('tenant_role_assignments')
                ->where('tenant_role_id', $role->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
            if ($activeAssignments) {
                throw FoundationException::conflict('role_in_use', 'A role with active assignments cannot be deleted.');
            }
            DB::table('tenant_role_permissions')->where('tenant_role_id', $role->id)->delete();
            $role->delete();
            $this->audit->writeTenant('role.deleted', 'succeeded', $context, targetType: 'tenant_role', targetId: $role->id);
        });

        return $this->empty();
    }

    private function mapRole(TenantRole $role): array
    {
        return [
            'id' => (string) $role->id,
            'tenant_id' => $role->tenant_id,
            'name' => $role->name_en ?? $role->name,
            'name_en' => $role->name_en ?? $role->name,
            'name_ar' => $role->name_ar ?? $role->name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'permissions' => $role->relationLoaded('permissions') ? $role->permissions->pluck('key')->values()->all() : [],
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }

    private function findTenantRole(string $tenantId, string $roleId): TenantRole
    {
        $resolved = request()->route('role_id');

        if (! is_string($resolved) || $resolved === '') {
            $resolved = $roleId;
        }

        abort_if($resolved === '', 404);

        return TenantRole::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereKey($resolved)
            ->firstOrFail();
    }
}
