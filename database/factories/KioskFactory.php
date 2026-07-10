<?php

namespace Database\Factories;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Kiosk> */
final class KioskFactory extends Factory
{
    protected $model = Kiosk::class;

    public function definition(): array
    {
        return [
            'device_name' => 'Kiosk '.$this->faker->word(),
            'device_code' => strtoupper(Str::random(8)),
            'location_label' => null,
            'status' => 'registered',
            'printer_status' => 'unknown',
            'last_heartbeat_at' => null,
            'confirmation_required' => false,
            'confirmation_code_hash' => null,
            'retired_at' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(['status' => 'online', 'last_heartbeat_at' => now()]);
    }

    public function retired(): static
    {
        return $this->state(['status' => 'retired', 'retired_at' => now()]);
    }
}
