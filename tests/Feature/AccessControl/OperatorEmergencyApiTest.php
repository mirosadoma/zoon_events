<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AccessEvent;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\EmergencyEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-emergency')]
final class OperatorEmergencyApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_operator_can_raise_and_clear_emergency(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.emergency.manage']);
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = "/api/v1/tenant/events/{$acs['event']->id}/acs/emergency";

        $this->postJson($url, ['action' => 'raise', 'zone_id' => $acs['zone']->id])
            ->assertUnauthorized();

        $this->actingAsScanner($scan);

        $raise = $this->postJson(
            $url,
            ['action' => 'raise', 'zone_id' => $acs['zone']->id],
            $this->acsTenantHeaders($scan, 'acs-emergency-raise-'.Str::ulid()),
        );

        $raise->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'zone_id', 'signal_source', 'behavior_applied', 'raised_at', 'cleared_at'],
            ])
            ->assertJsonPath('data.zone_id', $acs['zone']->id)
            ->assertJsonPath('data.cleared_at', null);

        $emergencyId = $raise->json('data.id');

        $clear = $this->postJson(
            $url,
            ['action' => 'clear', 'zone_id' => $acs['zone']->id],
            $this->acsTenantHeaders($scan, 'acs-emergency-clear-'.Str::ulid()),
        );

        $clear->assertOk();

        $emergency = EmergencyEvent::query()->findOrFail($emergencyId);
        self::assertNotNull($emergency->cleared_at);
        self::assertNull(AccessEvent::query()->where('event_type', 'emergency')->latest('created_at')->value('reason_code'));
    }

    public function test_event_wide_emergency_records_mixed_behavior_when_zones_disagree(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.emergency.manage']);
        $acs = $this->createAcsAuthorizationFixture($scan);

        AcsZone::factory()->create([
            'tenant_id' => $acs['event']->tenant_id,
            'event_id' => $acs['event']->id,
            'emergency_egress_mode' => 'fail_closed',
        ]);

        $this->actingAsScanner($scan);

        $this->postJson(
            "/api/v1/tenant/events/{$acs['event']->id}/acs/emergency",
            ['action' => 'raise'],
            $this->acsTenantHeaders($scan, 'acs-emergency-event-wide-'.Str::ulid()),
        )
            ->assertOk()
            ->assertJsonPath('data.zone_id', null)
            ->assertJsonPath('data.behavior_applied', 'mixed');
    }

    public function test_operator_emergency_requires_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $url = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/emergency";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->postJson(
                $url,
                ['action' => 'raise'],
                $this->acsTenantHeaders($scan, 'acs-emergency-forbidden-'.Str::ulid()),
            ),
            403,
            'acs_emergency_not_permitted',
        );
    }
}
