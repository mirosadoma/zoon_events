<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-sensitive-data')]
final class SensitiveDataTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_sensitive_data_view_is_audited_and_delete_requires_manage_permission(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.data.view']);
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;
        $verification = $this->verificationWithArtifact($scan);
        $url = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity/data";

        $this->actingAsScanner($scan);

        $this->getJson($url, $this->tenantHeaders($scan['fixture']['tenant']))
            ->assertOk()
            ->assertJsonPath('data.verification.id', (string) $verification->id)
            ->assertJsonMissingPath('data.artifacts.0.storage_reference')
            ->assertJsonMissing(['provider_reference', 'ciphertext', 'raw']);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_data.viewed')->exists(),
        );

        $this->assertProblemDetails(
            $this->deleteJson(
                $url,
                ['reason' => 'data_subject_request'],
                array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'identity-delete-forbidden-'.Str::ulid()]),
            ),
            403,
            'forbidden',
        );
    }

    public function test_compliance_admin_can_delete_sensitive_data_and_action_is_audited(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.data.view', 'identity.data.manage']);
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;
        $verification = $this->verificationWithArtifact($scan);
        $url = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity/data";

        $this->actingAsScanner($scan);

        $this->deleteJson(
            $url,
            ['reason' => 'data_subject_request'],
            array_merge($this->tenantHeaders($scan['fixture']['tenant']), ['Idempotency-Key' => 'identity-delete-'.Str::ulid()]),
        )->assertOk()
            ->assertJsonPath('data.status', IdentityVerificationStatus::PENDING);

        $verification->refresh();
        self::assertNull($verification->provider_reference);
        self::assertNull($verification->verified_name);
        self::assertSame('data_subject_request', $verification->rejection_reason);

        $artifact = IdentityBiometricArtifact::query()->where('verification_id', $verification->id)->firstOrFail();
        self::assertSame('purged', $artifact->storage_reference);
        self::assertNotNull($artifact->purged_at);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_data.deleted')->exists(),
        );
    }

    /** @param array{fixture:array<string,mixed>,credential:Credential} $scan */
    private function verificationWithArtifact(array $scan): IdentityVerification
    {
        $verification = IdentityVerification::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $scan['fixture']['event']->id,
            'attendee_id' => $scan['credential']->attendee_id,
            'method' => IdentityVerificationMethod::GOVERNMENT_IDENTITY,
            'status' => IdentityVerificationStatus::GOV_VERIFIED,
            'provider' => 'mock',
            'provider_reference' => 'gov-sensitive-test',
            'verified_name' => 'Sensitive Subject',
            'verified_nationality' => 'SA',
            'verified_at' => CarbonImmutable::now(),
        ]);

        IdentityBiometricArtifact::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'verification_id' => $verification->id,
            'artifact_type' => 'template',
            'storage_reference' => '{"ciphertext":"sensitive-template"}',
            'liveness_result' => 'passed',
            'retention_until' => CarbonImmutable::now()->addDays(30),
            'created_at' => CarbonImmutable::now(),
        ]);

        return $verification;
    }
}
