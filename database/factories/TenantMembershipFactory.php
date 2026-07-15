<?php

namespace Database\Factories;

use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TenantMembership> */
class TenantMembershipFactory extends Factory
{
    protected $model = TenantMembership::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'user_id' => 1,
            'status' => LifecycleStatus::Active->value,
            'created_by_user_id' => 1,
        ];
    }
}
