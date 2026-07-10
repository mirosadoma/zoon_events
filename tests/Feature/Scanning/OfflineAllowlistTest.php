<?php

namespace Tests\Feature\Scanning;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsTenantFixtures;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('phase-2')]
#[Group('offline-scanning')]
final class OfflineAllowlistTest extends Phase2MySqlTestCase
{
    use BuildsTenantFixtures;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_allowlist_export_contains_bounded_digest_only_entries(): void
    {
        $scan = $this->createIssuedCredentialScanFixture(['checkin.scan.submit']);
        $eventId = $scan['fixture']['event']->id;
        $credentialId = $scan['credential']->id;
        $url = "/api/v1/tenant/events/{$eventId}/offline-allowlist";

        $this->actingAsScanner($scan);
        $default = $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->json('data');

        self::assertArrayHasKey('issued_at', $default);
        self::assertArrayHasKey('expires_at', $default);
        self::assertArrayHasKey('entries_digest', $default);
        self::assertNotEmpty($default['entries']);
        self::assertSame(
            hash('sha256', $credentialId),
            $default['entries'][0]['credential_reference_digest'],
        );

        $encoded = json_encode($default);
        self::assertStringNotContainsString($scan['token'], $encoded);
        self::assertStringNotContainsString($credentialId, $encoded);
        self::assertStringNotContainsString($scan['credential']->attendee_id, $encoded);

        $this->actingAsScanner($scan);
        $bounded = $this->getJson($url.'?window_minutes=30', $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->json('data');

        $issued = new \DateTimeImmutable($bounded['issued_at']);
        $expires = new \DateTimeImmutable($bounded['expires_at']);
        self::assertSame(30, (int) round(($expires->getTimestamp() - $issued->getTimestamp()) / 60));
    }
}
