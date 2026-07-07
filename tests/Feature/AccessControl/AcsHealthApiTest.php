<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Testing\FakeAcsAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-health')]
final class AcsHealthApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_health_endpoint_returns_integration_lane_and_emergency_state(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.health.view', 'acs.emergency.manage']);
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = "/api/v1/tenant/events/{$acs['event']->id}/acs/health";

        $acs['lane']->forceFill(['last_seen_at' => now()->subSeconds(30), 'health_status' => 'online'])->save();
        AcsLane::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'zone_id' => $acs['zone']->id,
            'last_seen_at' => now()->subHours(3),
            'health_status' => 'online',
        ]);

        app(FakeAcsAdapter::class)->forceHealth('degraded', 'mock_latency');

        $this->getJson($url)->assertUnauthorized();

        $this->actingAsScanner($scan);

        $response = $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'integration_status',
                    'active_emergency',
                    'lanes' => [['lane_id', 'health_status', 'last_seen_at']],
                ],
            ]);

        self::assertSame('degraded', $response->json('data.integration_status'));
        self::assertFalse($response->json('data.active_emergency'));

        $lanes = collect($response->json('data.lanes'));
        self::assertTrue($lanes->contains(fn (array $lane): bool => $lane['health_status'] === 'online'));
        self::assertTrue($lanes->contains(fn (array $lane): bool => $lane['health_status'] === 'offline'));

        $this->postJson(
            "/api/v1/tenant/events/{$acs['event']->id}/acs/emergency",
            ['action' => 'raise', 'zone_id' => $acs['zone']->id],
            $this->acsTenantHeaders($scan, 'health-emergency-'.Str::ulid()),
        )->assertOk();

        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonPath('data.active_emergency', true);
    }

    public function test_health_endpoint_requires_acs_health_view_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $url = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/health";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant'])),
            403,
            'acs_events_not_permitted',
        );
    }
}
