<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Application\Actions\ChangeTenantStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PlatformTenantController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly ChangeTenantStatus $changeTenantStatus,
        private readonly CursorPaginator $paginator,
        private readonly AuditWriter $audit,
    ) {}

    public function index(Request $request)
    {
        $page = $this->paginator->paginate(Tenant::query(), 'platform:tenants', [], $request->string('cursor')->toString(), $request->integer('page_size', 50));

        return $this->success(
            collect($page->items)->map(fn (Tenant $tenant): array => $this->mapTenant($tenant))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function show(string $tenantId)
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        return $this->success($this->mapTenant($tenant));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'organization_name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $tenant = DB::transaction(function () use ($validated, $actor): Tenant {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => LifecycleStatus::Active,
                'preferred_locale' => 'ar',
                'created_by_user_id' => $actor->id,
            ]);

            $slug = Str::slug($validated['organization_name']);
            $baseSlug = $slug;
            $counter = 1;
            while (Tenant::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $tenant = Tenant::query()->create([
                'name' => $validated['organization_name'],
                'slug' => $slug,
                'status' => 'active',
                'organization_type' => 'organizer',
                'default_locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'data_residency_region' => 'sa',
                'policy_profile' => ['reason' => 'Created by platform admin'],
                'created_by_user_id' => $actor->id,
            ]);

            $membership = TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'status' => 'active',
                'created_by_user_id' => $actor->id,
            ]);

            $role = TenantRole::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Tenant Administrator',
                'description' => 'Full tenant administration.',
                'is_system' => true,
                'created_by_user_id' => $actor->id,
            ]);

            foreach (Permission::query()->where('scope', 'tenant')->pluck('id') as $permissionId) {
                DB::table('tenant_role_permissions')->insert([
                    'tenant_id' => $tenant->id,
                    'tenant_role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'granted_by_user_id' => $actor->id,
                    'created_at' => now(),
                ]);
            }

            DB::table('tenant_role_assignments')->insert([
                'tenant_id' => $tenant->id,
                'tenant_membership_id' => $membership->id,
                'tenant_role_id' => $role->id,
                'granted_by_user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $tenant;
        });

        $this->audit->writePlatform(
            'tenant.created',
            'succeeded',
            $actor,
            targetType: 'tenant',
            targetId: $tenant->id,
            metadata: ['organizer_email' => $validated['email']],
        );

        return $this->success($this->mapTenant($tenant->refresh()), 201);
    }

    public function update(Request $request, string $tenantId)
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'status' => ['sometimes', 'in:active,suspended,deactivated'],
            'organization_type' => ['sometimes', 'in:organizer,venue_owner,hybrid'],
            'default_locale' => ['sometimes', 'in:en,ar'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'data_residency_region' => ['sometimes', 'string', 'max:64'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $tenant = $this->changeTenantStatus->handle(
            $tenant,
            collect($validated)->except('reason')->all(),
            $actor,
            $validated['reason'],
        );

        return $this->success($this->mapTenant($tenant));
    }

    public function destroy(Request $request, string $tenantId)
    {
        $tenant = Tenant::query()->findOrFail($tenantId);

        /** @var User $actor */
        $actor = $request->user();

        $this->audit->writePlatform(
            'tenant.deleted',
            'succeeded',
            $actor,
            targetType: 'tenant',
            targetId: $tenant->id,
            metadata: ['name' => $tenant->name],
        );

        DB::transaction(function () use ($tenant): void {
            DB::table('tenant_role_assignments')->where('tenant_id', $tenant->id)->delete();
            DB::table('tenant_role_permissions')->where('tenant_id', $tenant->id)->delete();
            TenantRole::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            TenantMembership::query()->where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });

        return $this->success(null, 204);
    }

    private function mapTenant(Tenant $tenant): array
    {
        $admin = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        $adminUser = $admin ? User::query()->find($admin->user_id) : null;

        return [
            'id' => (string) $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status->value,
            'organization_type' => $tenant->organization_type->value,
            'default_locale' => $tenant->default_locale,
            'timezone' => $tenant->timezone,
            'data_residency_region' => $tenant->data_residency_region,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'admin' => $adminUser ? [
                'id' => (string) $adminUser->id,
                'name' => $adminUser->name,
                'email' => $adminUser->email,
                'phone' => $adminUser->phone ?? null,
            ] : null,
        ];
    }
}
