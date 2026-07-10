<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Application\Actions\RegisterAcsIntegrationCredentialAction;
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
final class AcsEmergencyCallbackApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_acs_emergency_callback_raises_and_clears_emergency(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = '/api/v1/acs/v1/emergency';

        $this->postJson($url, [
            'action' => 'raise',
            'external_acs_zone_id' => $acs['zone']->external_acs_zone_id,
            'signal_source' => 'fire_alarm',
            'occurred_at' => now()->toIso8601String(),
        ])->assertUnauthorized();

        $raise = $this->postJson($url, [
            'action' => 'raise',
            'external_acs_zone_id' => $acs['zone']->external_acs_zone_id,
            'signal_source' => 'fire_alarm',
            'occurred_at' => now()->toIso8601String(),
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-em-callback-raise-'.Str::ulid()));

        $raise->assertAccepted()
            ->assertJsonPath('data.zone_id', $acs['zone']->id)
            ->assertJsonPath('data.cleared_at', null);

        $clear = $this->postJson($url, [
            'action' => 'clear',
            'external_acs_zone_id' => $acs['zone']->external_acs_zone_id,
            'occurred_at' => now()->toIso8601String(),
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-em-callback-clear-'.Str::ulid()));

        $clear->assertAccepted();
    }

    public function test_acs_emergency_callback_requires_emergency_ingest_capability(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);

        $authorizeOnly = app(RegisterAcsIntegrationCredentialAction::class)->execute(
            $acs['event']->tenant_id,
            $acs['event']->id,
            'Authorize only',
            ['authorize'],
        )['secret'];

        $this->assertProblemDetails(
            $this->postJson('/api/v1/acs/v1/emergency', [
                'action' => 'raise',
                'external_acs_zone_id' => $acs['zone']->external_acs_zone_id,
                'occurred_at' => now()->toIso8601String(),
            ], $this->acsIntegrationHeaders($authorizeOnly, 'acs-em-cap-'.Str::ulid())),
            403,
            'acs_capability_denied',
        );
    }
}
