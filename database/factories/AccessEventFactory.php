<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccessEvent> */
final class AccessEventFactory extends Factory
{
    protected $model = AccessEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => 'decision',
            'credential_id' => null,
            'zone_id' => null,
            'lane_id' => null,
            'direction' => 'entry',
            'decision' => 'allow',
            'reason_code' => 'allowed',
            'source' => 'acs_gate',
            'external_event_id' => null,
            'scan_event_id' => null,
            'occurred_at' => now(),
        ];
    }
}
