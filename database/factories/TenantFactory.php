<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company().'-'.fake()->numerify('###')),
            'status' => LifecycleStatus::Active->value,
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'data_residency_region' => 'ksa-central',
            'policy_profile' => ['retention_policy' => 'default'],
            'created_by_user_id' => User::factory(),
        ];
    }
}
