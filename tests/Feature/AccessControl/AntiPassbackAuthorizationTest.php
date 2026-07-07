<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsAuthorizationRule;
use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsZone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-anti-passback')]
final class AntiPassbackAuthorizationTest extends Phase4MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_anti_passback_denies_reentry_until_exit_is_recorded(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $acs['zone']->forceFill(['anti_passback_enabled' => true])->save();

        $this->postAccessEventCallback(
            $acs,
            'ap-entry-'.Str::lower((string) Str::ulid()),
            'entry',
            $acs['token'],
        )->assertAccepted();

        $deny = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'ap-deny-'.Str::ulid()));

        $deny->assertOk()
            ->assertJsonPath('data.decision', 'deny')
            ->assertJsonPath('data.reason_code', 'anti_passback_violation');

        $this->postAccessEventCallback(
            $acs,
            'ap-exit-'.Str::lower((string) Str::ulid()),
            'exit',
            $acs['token'],
        )->assertAccepted();

        $allow = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'ap-allow-'.Str::ulid()));

        $allow->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'allowed');
    }

    public function test_anti_passback_exempt_rule_and_disabled_zone_skip_violation(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $acs['zone']->forceFill(['anti_passback_enabled' => true])->save();

        $this->postAccessEventCallback(
            $acs,
            'ap-exempt-entry-'.Str::lower((string) Str::ulid()),
            'entry',
            $acs['token'],
        )->assertAccepted();

        AcsAuthorizationRule::query()->where('id', $acs['rule']->id)->update(['anti_passback_exempt' => true]);

        $exempt = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'ap-exempt-'.Str::ulid()));

        $exempt->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'allowed');

        $disabledScan = $this->createIssuedCredentialScanFixture();
        $disabledAcs = $this->createAcsAuthorizationFixture($disabledScan);
        AcsZone::query()->where('id', $disabledAcs['zone']->id)->update(['anti_passback_enabled' => false]);

        $this->postAccessEventCallback(
            $disabledAcs,
            'ap-disabled-entry-'.Str::lower((string) Str::ulid()),
            'entry',
            $disabledAcs['token'],
        )->assertAccepted();

        $allowed = $this->postJson('/api/v1/acs/v1/authorize', [
            'external_acs_lane_id' => $disabledAcs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $disabledAcs['token'],
        ], $this->acsIntegrationHeaders($disabledAcs['secret'], 'ap-disabled-'.Str::ulid()));

        $allowed->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'allowed');
    }
}
