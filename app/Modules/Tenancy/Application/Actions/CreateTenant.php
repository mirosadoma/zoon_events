<?php

namespace App\Modules\Tenancy\Application\Actions;

use App\Models\User;
use App\Modules\Audit\Application\AuditedTransaction;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Domain\Events\TenantCreated;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Support\Facades\DB;

final class CreateTenant
{
    public function __construct(
        private readonly AuditedTransaction $transaction,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(array $data, User $actor): Tenant
    {
        $tenant = $this->transaction->run(function () use ($data, $actor): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'], 'slug' => $data['slug'], 'status' => 'active',
                'organization_type' => $data['organization_type'],
                'default_locale' => $data['default_locale'], 'timezone' => $data['timezone'],
                'data_residency_region' => $data['data_residency_region'],
                'policy_profile' => ['reason' => $data['reason']], 'created_by_user_id' => $actor->id,
            ]);
            $membership = TenantMembership::query()->create([
                'tenant_id' => $tenant->id, 'user_id' => $data['initial_admin_user_id'],
                'status' => 'active', 'created_by_user_id' => $actor->id,
            ]);
            $role = TenantRole::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'name' => 'Tenant Administrator',
                'description' => 'Full tenant foundation administration.', 'is_system' => true,
                'created_by_user_id' => $actor->id,
            ]);
            foreach (Permission::query()->where('scope', 'tenant')->pluck('id') as $permissionId) {
                DB::table('tenant_role_permissions')->insert(['tenant_id' => $tenant->id, 'tenant_role_id' => $role->id, 'permission_id' => $permissionId, 'granted_by_user_id' => $actor->id, 'created_at' => now()]);
            }
            DB::table('tenant_role_assignments')->insert([
                'tenant_id' => $tenant->id,
                'tenant_membership_id' => $membership->id, 'tenant_role_id' => $role->id,
                'granted_by_user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $tenant;
        }, fn (Tenant $tenant) => $this->audit->writePlatform('tenant.created', 'succeeded', $actor, targetType: 'tenant', targetId: $tenant->id, metadata: ['reason' => $data['reason']]));

        DB::afterCommit(fn () => event(new TenantCreated($tenant->id, $actor->id, $data['reason'])));

        return $tenant->refresh();
    }
}
