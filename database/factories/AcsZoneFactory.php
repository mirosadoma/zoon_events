<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AcsZone> */
final class AcsZoneFactory extends Factory
{
    protected $model = AcsZone::class;

    public function definition(): array
    {
        return [
            'name' => 'Zone '.$this->faker->word(),
            'external_acs_zone_id' => 'zone-'.Str::lower((string) Str::ulid()),
            'anti_passback_enabled' => false,
            'unavailability_mode' => 'fail_closed',
            'emergency_egress_mode' => 'fail_open',
            'status' => 'active',
        ];
    }
}
