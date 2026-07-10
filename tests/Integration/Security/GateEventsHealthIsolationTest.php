<?php

namespace Tests\Integration\Security;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
#[Group('acs-health')]
final class GateEventsHealthIsolationTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_gate_events_and_health_never_expose_foreign_tenant_or_event_data(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scanA = $this->createIssuedCredentialScanFixture(['acs.events.view', 'acs.health.view']);
        $scanB = $this->createIssuedCredentialScanFixture(['acs.events.view', 'acs.health.view']);
        $acsA = $this->createAcsAuthorizationFixture($scanA);
        $acsB = $this->createAcsAuthorizationFixture($scanB);

        AccessEvent::factory()->create([
            'tenant_id' => $acsA['event']->tenant_id,
            'event_id' => $acsA['event']->id,
            'zone_id' => $acsA['zone']->id,
            'lane_id' => $acsA['lane']->id,
            'reason_code' => 'allowed',
            'occurred_at' => now(),
        ]);

        AccessEvent::factory()->create([
            'tenant_id' => $acsB['event']->tenant_id,
            'event_id' => $acsB['event']->id,
            'zone_id' => $acsB['zone']->id,
            'lane_id' => $acsB['lane']->id,
            'reason_code' => 'foreign-marker',
            'occurred_at' => now(),
        ]);

        $this->actingAsScanner($scanA);

        $events = $this->getJson(
            "/api/v1/tenant/events/{$acsA['event']->id}/acs/gate-events",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()->json('data');

        self::assertNotEmpty($events);
        foreach ($events as $event) {
            self::assertSame($acsA['event']->id, AccessEvent::query()->findOrFail($event['id'])->event_id);
        }

        $this->getJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/acs/gate-events",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()->assertJsonCount(0, 'data');

        $health = $this->getJson(
            "/api/v1/tenant/events/{$acsA['event']->id}/acs/health",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()->json('data.lanes');

        $laneIds = collect($health)->pluck('lane_id')->all();
        self::assertContains($acsA['lane']->id, $laneIds);
        self::assertNotContains($acsB['lane']->id, $laneIds);

        $this->getJson(
            "/api/v1/tenant/events/{$scanB['fixture']['event']->id}/acs/health",
            $this->tenantHeaders($scanA['fixture']['tenant']),
        )->assertOk()
            ->assertJsonPath('data.lanes', [])
            ->assertJsonPath('data.active_emergency', false);
    }
}
