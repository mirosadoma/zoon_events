<?php

namespace Tests\Contract\Phase4;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-health')]
final class GateEventsHealthApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_gate_events_and_health_routes_match_contract(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $eventsRoute = $routes->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/acs/gate-events'
                && in_array('GET', $route->methods(), true),
        );
        $healthRoute = $routes->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/tenant/events/{event_id}/acs/health'
                && in_array('GET', $route->methods(), true),
        );

        self::assertNotNull($eventsRoute);
        self::assertNotNull($healthRoute);
        self::assertContains('auth:sanctum', $eventsRoute->gatherMiddleware());
        self::assertContains('auth:sanctum', $healthRoute->gatherMiddleware());
    }

    public function test_gate_events_and_health_contract_cases(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.events.view', 'acs.health.view']);
        $acs = $this->createAcsAuthorizationFixture($scan);
        $eventsUrl = "/api/v1/tenant/events/{$acs['event']->id}/acs/gate-events";
        $healthUrl = "/api/v1/tenant/events/{$acs['event']->id}/acs/health";

        $this->getJson($eventsUrl)->assertUnauthorized();
        $this->getJson($healthUrl)->assertUnauthorized();

        $this->actingAsScanner($scan);

        $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'health-contract-auth-'.Str::ulid()))->assertOk();

        $this->getJson($eventsUrl, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'event_type', 'reason_code', 'occurred_at']]]);

        $this->getJson("{$eventsUrl}?limit=10", $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(min(10, count($this->getJson($eventsUrl, $this->tenantHeaders($scan['fixture']['tenant']))->json('data'))), 'data');

        $this->getJson($healthUrl, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['integration_status', 'active_emergency', 'lanes'],
            ]);
    }
}
