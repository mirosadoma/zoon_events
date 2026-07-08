<?php

namespace Tests\Feature\IdentityVerification;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-isolation')]
final class RequirementsIsolationTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_requirement_of_tenant_a_is_not_readable_or_writable_by_tenant_b(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $tenantA = $this->createIssuedCredentialScanFixture(['identity.configure']);
        $tenantB = $this->createIssuedCredentialScanFixture(['identity.configure']);

        $eventA = (string) $tenantA['fixture']['event']->id;
        $eventB = (string) $tenantB['fixture']['event']->id;
        $urlA = "/api/v1/tenant/events/{$eventA}/identity/requirements";
        $urlB = "/api/v1/tenant/events/{$eventB}/identity/requirements";

        $this->actingAsScanner($tenantA);
        $this->putJson(
            $urlA,
            ['level' => 'required_before_gate', 'face_fallback_enabled' => true],
            array_merge($this->tenantHeaders($tenantA['fixture']['tenant']), ['Idempotency-Key' => 'identity-req-tenant-a-'.Str::ulid()]),
        )->assertOk();

        $this->actingAsScanner($tenantB);

        $this->assertProblemDetails(
            $this->getJson($urlA, $this->tenantHeaders($tenantB['fixture']['tenant'])),
            404,
            'resource_not_found',
        );
        $this->assertProblemDetails(
            $this->putJson(
                $urlA,
                ['level' => 'required_before_credential', 'face_fallback_enabled' => false],
                array_merge($this->tenantHeaders($tenantB['fixture']['tenant']), ['Idempotency-Key' => 'identity-req-tenant-b-'.Str::ulid()]),
            ),
            404,
            'resource_not_found',
        );

        $this->getJson($urlB, $this->tenantHeaders($tenantB['fixture']['tenant']))
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
