<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-emergency')]
final class EmergencyEgressAuthorizationTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_active_emergency_fail_open_bypasses_rules_and_resumes_after_clear(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.emergency.manage']);
        $acs = $this->createAcsAuthorizationFixture($scan);
        $acs['zone']->forceFill([
            'anti_passback_enabled' => true,
            'emergency_egress_mode' => 'fail_open',
        ])->save();

        AcsAuthorizationRule::query()->where('id', $acs['rule']->id)->delete();

        $this->postAccessEventCallback(
            $acs,
            'em-entry-'.Str::lower((string) Str::ulid()),
            'entry',
            $acs['token'],
        )->assertAccepted();

        $this->actingAsScanner($scan);
        $this->postJson(
            "/api/v1/tenant/events/{$acs['event']->id}/acs/emergency",
            ['action' => 'raise', 'zone_id' => $acs['zone']->id],
            $this->acsTenantHeaders($scan, 'em-auth-raise-'.Str::ulid()),
        )->assertOk();

        $emergencyAllow = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'em-auth-allow-'.Str::ulid()));

        $emergencyAllow->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'emergency_fail_open');

        $this->postJson(
            "/api/v1/tenant/events/{$acs['event']->id}/acs/emergency",
            ['action' => 'clear', 'zone_id' => $acs['zone']->id],
            $this->acsTenantHeaders($scan, 'em-auth-clear-'.Str::ulid()),
        )->assertOk();

        $deny = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'em-auth-deny-'.Str::ulid()));

        $deny->assertOk()
            ->assertJsonPath('data.decision', 'deny')
            ->assertJsonPath('data.reason_code', 'zone_not_permitted');
    }
}
