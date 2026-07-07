<?php

namespace Database\Factories;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsIntegrationCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcsIntegrationCredential>
 */
final class AcsIntegrationCredentialFactory extends Factory
{
    protected $model = AcsIntegrationCredential::class;

    public function definition(): array
    {
        return [
            'name' => 'ACS Integration',
            'secret_hash' => hash('sha256', 'test-secret'),
            'capabilities' => ['authorize', 'event.ingest', 'emergency.ingest'],
            'status' => 'active',
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
        ];
    }

    public function withSecret(string $plainSecret): static
    {
        return $this->state(fn (): array => [
            'secret_hash' => hash('sha256', $plainSecret),
        ]);
    }
}
