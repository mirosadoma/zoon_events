<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcsAuthorizationRule> */
final class AcsAuthorizationRuleFactory extends Factory
{
    protected $model = AcsAuthorizationRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->tenant_id,
            'event_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->event_id,
            'zone_id' => AcsZone::factory(),
            'ticket_type_id' => null,
            'attendee_type' => null,
            'lane_id' => null,
            'access_direction' => 'entry',
            'anti_passback_exempt' => false,
            'valid_from' => null,
            'valid_until' => null,
            'status' => 'active',
        ];
    }
}
