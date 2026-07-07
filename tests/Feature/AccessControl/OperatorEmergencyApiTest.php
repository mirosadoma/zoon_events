<?php

namespace Tests\Feature\AccessControl;

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
