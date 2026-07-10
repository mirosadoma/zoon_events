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
#[Group('identity-requirements')]
final class RequirementsTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_requirements_endpoint_requires_identity_configure_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = (string) $scan['fixture']['event']->id;
        $url = "/api/v1/tenant/events/{$eventId}/identity/requirements";

        $this->getJson($url)->assertUnauthorized();

        $this->actingAsScanner($scan);

        $this->assertProblemDetails(
            $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant'])),
            403,
            'forbidden',
        );

        $this->assertProblemDetails(
            $this->putJson(
                $url,
                ['level' => 'required_before_gate', 'face_fallback_enabled' => true],
                array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'identity-req-no-perm-'.Str::ulid()]),
            ),
            403,
            'forbidden',
        );
    }

    public function test_requirements_index_and_update_return_documented_shapes(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.configure']);
        $eventId = (string) $scan['fixture']['event']->id;
        $ticketId = (string) $scan['fixture']['ticket']->id;
        $url = "/api/v1/tenant/events/{$eventId}/identity/requirements";

        $this->actingAsScanner($scan);

        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->putJson(
            $url,
            [
                'ticket_type_id' => $ticketId,
                'level' => 'required_before_credential',
                'face_fallback_enabled' => true,
            ],
            array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'identity-req-update-'.Str::ulid()]),
        )->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'event_id', 'ticket_type_id', 'level', 'face_fallback_enabled'],
            ])
            ->assertJsonPath('data.ticket_type_id', $ticketId)
            ->assertJsonPath('data.level', 'required_before_credential');

        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
