<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AcsLane> */
final class AcsLaneFactory extends Factory
{
    protected $model = AcsLane::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->tenant_id,
            'event_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->event_id,
            'zone_id' => AcsZone::factory(),
            'name' => 'Lane '.$this->faker->word(),
            'external_acs_lane_id' => 'lane-'.Str::lower((string) Str::ulid()),
            'gate_type' => 'turnstile',
            'access_direction' => 'entry',
            'is_admission_lane' => false,
            'status' => 'active',
            'health_status' => 'online',
            'last_seen_at' => null,
        ];
    }

    public function admission(): static
    {
        return $this->state(['is_admission_lane' => true]);
    }
}
