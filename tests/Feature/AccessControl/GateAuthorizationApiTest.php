<?php

namespace Tests\Feature\AccessControl;

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

    public function test_authorize_route_matches_contract(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $route): bool => $route->uri() === 'api/v1/acs/v1/authorize'
                && in_array('POST', $route->methods(), true),
        );

        self::assertNotNull($route);
        self::assertContains('acs.integration', $route->gatherMiddleware());
        self::assertContains('acs.capability:authorize', $route->gatherMiddleware());
    }

    public function test_authorize_returns_documented_problem_and_success_responses(): void
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
                'external_acs_lane_id' => 'unknown-lane',
                'direction' => 'entry',
                'credential_reference' => $acs['token'],
            ], $this->acsIntegrationHeaders($acs['secret'], 'acs-lane-'.Str::ulid())),
            404,
            'acs_lane_unmapped',
        );

        $response = $this->postJson($url, [
            'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
            'direction' => 'entry',
            'credential_reference' => $acs['token'],
        ], $this->acsIntegrationHeaders($acs['secret'], 'acs-authorize-'.Str::ulid()));

        $response->assertOk()
            ->assertJsonPath('data.decision', 'allow')
            ->assertJsonPath('data.reason_code', 'allowed')
            ->assertJsonStructure(['data' => ['decision', 'reason_code', 'access_event_id', 'scan_event_id']]);

        $body = json_encode($response->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret', strtolower($body));
        self::assertStringNotContainsString($acs['token'], $body);

        $eventsOnlySecret = $this->acsIntegrationWithoutAuthorizeCapability($acs['event']);
        $this->assertProblemDetails(
            $this->postJson($url, [
                'external_acs_lane_id' => $acs['lane']->external_acs_lane_id,
                'direction' => 'entry',
                'credential_reference' => $acs['token'],
            ], $this->acsIntegrationHeaders($eventsOnlySecret, 'acs-capability-'.Str::ulid())),
            403,
            'acs_capability_denied',
        );
    }
}
