<?php

namespace Tests\Contract\Phase4;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\CreatesPhase4AcsFixture;
use Tests\Support\Phase4MySqlTestCase;

#[Group('phase-4')]
#[Group('acs-authorization')]
final class GateAuthorizationApiTest extends Phase4MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use CreatesPhase4AcsFixture;
    use DatabaseTransactions;

    public function test_request_gate_authorization_matches_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/acs/v1/authorize'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('acs.integration.clear', $route->gatherMiddleware());
        self::assertContains('idempotency', $route->gatherMiddleware());
    }

    public function test_request_gate_authorization_contract_cases(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $scan = $this->createIssuedCredentialScanFixture();
        $acs = $this->createAcsAuthorizationFixture($scan);
        $url = '/api/v1/acs/v1/authorize';

        $this->postJson($url, [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ])->assertUnauthorized();

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
                'direction' => 'entry',
                'credential_reference' => $acs['token'],
                'forged_field' => true,
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-contract-422-'.Str::ulid())),
            422,
            'validation_failed',
        );

        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_acs_lane_id' => 'missing-lane',
                'direction' => 'entry',
                'credential_reference' => $acs['token'],
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-contract-404-'.Str::ulid())),
            404,
            'acs_lane_unmapped',
        );

        $this->postJson($url, [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-contract-200-'.Str::ulid()))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['decision', 'reason_code', 'access_event_id', 'scan_event_id'],
            ]);
    }
}
