<?php

namespace Tests\Integration\Security;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
#[Group('acs-authorization')]
final class GateAuthorizationIsolationTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_cross_scope_lane_and_credential_are_rejected_like_unknown_targets(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $otherScan = $this->createIssuedCredentialScanFixture();
        $foreignZone = AcsZone::factory()->create([
            'tenant_id' => $otherScan['fixture']['tenant']->id,
            'event_id' => $otherScan['fixture']['event']->id,
        ]);
        $foreignLane = AcsLane::factory()->create([
            'tenant_id' => $otherScan['fixture']['tenant']->id,
            'event_id' => $otherScan['fixture']['event']->id,
            'zone_id' => $foreignZone->id,
        ]);

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/authorize', [
                'external_acs_lane_id' => $foreignLane->external_acs_lane_id,
                'direction' => 'entry',
                'credential_reference' => $acs['token'],
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-isolation-lane-'.Str::ulid())),
            404,
            'acs_lane_unmapped',
        );

        $foreignScan = $this->createIssuedCredentialScanFixture();
        $deny = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $foreignScan['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-isolation-credential-'.Str::ulid()));

        $deny->assertOk()
            ->assertJsonPath('data.decision', 'deny')
            ->assertJsonPath('data.reason_code', 'credential_unknown');
    }
}
