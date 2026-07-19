<?php

namespace Database\Factories;

use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'status' => LifecycleStatus::Active->value,
            'organization_type' => 'organizer',
            'default_locale' => 'en',
            'timezone' => 'Africa/Cairo',
            'data_residency_region' => 'eg',
            'created_by_user_id' => 1,
        ];
    }

    public function venueOwner(): static
    {
        return $this->state(['organization_type' => 'venue_owner']);
    }

    public function organizer(): static
    {
        return $this->state(['organization_type' => 'organizer']);
    }

    public function hybrid(): static
    {
        return $this->state(['organization_type' => 'hybrid']);
    }
}
