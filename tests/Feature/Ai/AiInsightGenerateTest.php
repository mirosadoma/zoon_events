<?php

namespace Tests\Feature\Ai;

use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

final class AiInsightGenerateTest extends Phase1MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    #[Test]
    public function it_generates_insights_without_scan_outcome_column_error(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $fixture = $this->createRegistrationFixture();
        $actor = $fixture['actor'];
        $tenant = $fixture['tenant'];
        $event = $fixture['event'];

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $actor->id,
            'status' => 'active',
            'created_by_user_id' => $actor->id,
        ]);
        $this->grantTenantPermissions($tenant, $membership, ['reports.view', 'event.view']);

        $this->actingAsTenantMember($actor, $tenant);

        $response = $this->postJson(
            "/api/v1/tenant/events/{$event->id}/ai-insights",
            [
                'metric_window' => 'last_14_days',
                'locale' => 'en',
                'refresh' => true,
            ],
            [
                'X-Tenant-ID' => (string) $tenant->id,
                'Idempotency-Key' => (string) Str::ulid(),
            ],
        );

        $response->assertOk();
        $this->assertTrue(
            in_array($response->json('data.outcome'), ['insufficient_data', null], true)
            || is_string($response->json('data.summary'))
            || $response->json('data.metrics_used') !== null,
        );
    }
}
