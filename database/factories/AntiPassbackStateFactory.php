<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AntiPassbackState;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AntiPassbackState> */
final class AntiPassbackStateFactory extends Factory
{
    protected $model = AntiPassbackState::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->tenant_id,
            'event_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->event_id,
            'credential_id' => Credential::factory(),
            'zone_id' => AcsZone::factory(),
            'state' => 'outside',
            'last_access_event_id' => null,
            'last_transition_at' => null,
        ];
    }
}
