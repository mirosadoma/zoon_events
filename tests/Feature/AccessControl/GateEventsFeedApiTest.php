<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
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
final class GateEventsFeedApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_gate_events_feed_returns_scoped_decisions_newest_first(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.events.view']);
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = "/api/v1/tenant/events/{$acs['event']->id}/acs/gate-events";

        $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'feed-auth-'.Str::ulid()))->assertOk();

        AccessEvent::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'event_type' => 'entry',
            'zone_id' => $acs['zone']->id,
            'lane_id' => $acs['lane']->id,
            'credential_id' => $acs['credential']->id,
            'direction' => 'entry',
            'decision' => 'n/a',
            'reason_code' => null,
            'occurred_at' => now()->subMinutes(5),
        ]);

        $this->getJson($url)->assertUnauthorized();

        $this->actingAsScanner($scan);

        $all = $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'event_type', 'reason_code', 'occurred_at']]]);

        self::assertGreaterThanOrEqual(2, count($all->json('data')));

        $since = now()->subMinutes(2)->toIso8601String();
        $this->getJson("{$url}?since=".urlencode($since).'&limit=1', $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_gate_events_feed_requires_acs_events_view_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $url = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/gate-events";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant'])),
            403,
            'acs_events_not_permitted',
        );
    }
}
