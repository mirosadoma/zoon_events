<?php

namespace Tests\Integration;

use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Testing\FakeAcsAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-unavailable')]
final class AcsUnavailableDeploymentParityTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_unavailable_acs_adapter_applies_zone_unavailability_modes_without_outbound_calls(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        config()->set('acs.default_acs_adapter', 'mock');

        $scan = $this->createIssuedCredentialScanFixture();
        $event = $scan['fixture']['event'];

        $failOpenZone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'unavailability_mode' => 'fail_open',
        ]);
        $failClosedZone = AcsZone::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'unavailability_mode' => 'fail_closed',
        ]);

        $openLane = AcsLane::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $failOpenZone->id,
        ]);
        $closedLane = AcsLane::factory()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->id,
            'zone_id' => $failClosedZone->id,
        ]);

        foreach ([$failOpenZone, $failClosedZone] as $zone) {
            AcsAuthorizationRule::factory()->create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'zone_id' => $zone->id,
                'ticket_type_id' => $scan['credential']->ticket_type_id,
            ]);
        }

        $registered = app(RegisterAcsIntegrationCredentialAction::class)
            ->execute($event->tenant_id, $event->id, 'Parity ACS', ['authorize']);
        $secret = $registered['secret'];

        $adapter = app(FakeAcsAdapter::class);
        $adapter->forceUnavailable(true);
        $this->app->instance(AcsAdapter::class, $adapter);
        self::assertSame(0, count($adapter->calls()));

        $allow = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $openLane->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $scan['token'],
        ], $this->acsIntegrationHeaders($secret, 'parity-open-'.Str::ulid()));

        $allow->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'acs_unavailable_fail_open');

        $deny = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $closedLane->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $scan['token'],
        ], $this->acsIntegrationHeaders($secret, 'parity-closed-'.Str::ulid()));

        $deny->assertOk()
            ->assertJsonPath('data.decision', 'deny')
            ->assertJsonPath('data.reason_code', 'acs_unavailable_fail_closed');

        self::assertSame(0, count($adapter->calls()));
        self::assertSame(2, AccessEvent::query()->where('event_id', $event->id)->count());
    }
}
