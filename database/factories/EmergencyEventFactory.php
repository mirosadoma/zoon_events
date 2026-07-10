<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmergencyEvent> */
final class EmergencyEventFactory extends Factory
{
    protected $model = EmergencyEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->tenant_id,
            'event_id' => fn (array $attributes) => AcsZone::query()->findOrFail($attributes['zone_id'])->event_id,
            'zone_id' => AcsZone::factory(),
            'signal_source' => 'operator',
            'behavior_applied' => 'fail_open',
            'raised_at' => now(),
            'cleared_at' => null,
        ];
    }
}
