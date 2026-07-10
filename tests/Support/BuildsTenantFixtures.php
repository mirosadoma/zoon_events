<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;

trait BuildsTenantFixtures
{
    /**
     * @return array{user: User, tenant: Tenant, membership: TenantMembership}
     */
    protected function createTenantMember(array $userAttributes = [], array $tenantAttributes = [], array $membershipAttributes = []): array
    {
        $creator = User::factory()->create();
        $user = User::factory()->create($userAttributes);
        $tenant = Tenant::factory()->create(array_merge(['created_by_user_id' => $creator->id], $tenantAttributes));
        $membership = TenantMembership::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_by_user_id' => $creator->id,
            'status' => LifecycleStatus::Active->value,
        ], $membershipAttributes));

        return compact('user', 'tenant', 'membership');
    }

    protected function actingAsTenantMember(User $user, Tenant $tenant): User
    {
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function tenantHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-ID' => $tenant->id,
            'Accept' => 'application/json',
        ];
    }
}
