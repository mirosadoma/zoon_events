<?php

namespace Database\Factories;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KioskSession> */
final class KioskSessionFactory extends Factory
{
    protected $model = KioskSession::class;

    public function definition(): array
    {
        return [
            'secret_hash'  => hash('sha256', 'fake-session-secret-'.$this->faker->uuid()),
            'confirmed_at' => null,
            'expires_at'   => now()->addDays(7),
            'revoked_at'   => null,
            'created_at'   => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['confirmed_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
