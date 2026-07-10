<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsIntegrationCredential;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-config')]
final class AcsIntegrationCredentialApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_integration_credential_registration_returns_secret_once_and_rotates_prior(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['acs.configure']);
        $url = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/integration-credentials";

        $this->postJson($url, [
            'name' => 'ACS Integration',
            'capabilities' => ['authorize', 'event.ingest', 'emergency.ingest'],
        ])->assertUnauthorized();

        $this->actingAsScanner($scan);

        $first = $this->postJson($url, [
            'name' => 'ACS Integration',
            'capabilities' => ['authorize', 'event.ingest', 'emergency.ingest'],
        ], $this->acsTenantHeaders($scan, 'acs-cred-first-'.Str::ulid()));

        $first->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'secret', 'capabilities', 'expires_at']]);

        $firstSecret = $first->json('data.secret');
        self::assertNotEmpty($firstSecret);
        $firstId = $first->json('data.id');

        $second = $this->postJson($url, [
            'name' => 'ACS Integration Rotated',
            'capabilities' => ['authorize'],
        ], $this->acsTenantHeaders($scan, 'acs-cred-second-'.Str::ulid()));

        $second->assertCreated();
        $secondSecret = $second->json('data.secret');
        self::assertNotSame($firstSecret, $secondSecret);

        $revoked = AcsIntegrationCredential::query()->findOrFail($firstId);
        self::assertSame('revoked', $revoked->status);
        self::assertNotNull($revoked->revoked_at);
    }

    public function test_integration_credential_registration_requires_acs_configure(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $url = "/api/v1/tenant/events/{$scan['fixture']['event']->id}/acs/integration-credentials";

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->postJson($url, [
                'name' => 'Denied',
                'capabilities' => ['authorize'],
            ], $this->acsTenantHeaders($scan, 'acs-cred-forbidden-'.Str::ulid())),
            403,
            'acs_config_not_permitted',
        );
    }
}
