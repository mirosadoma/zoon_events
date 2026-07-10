<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantMembership> */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        $creator = User::factory();

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'status' => LifecycleStatus::Active->value,
            'created_by_user_id' => $creator,
        ];
    }
}
