<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-residency')]
final class ResidencyTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_identity_api_resources_exclude_raw_biometric_and_government_payloads(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $scan = $this->createIssuedCredentialScanFixture(['identity.review', 'identity.data.view']);
        $eventId = (string) $scan['fixture']['event']->id;
        $attendeeId = (string) $scan['credential']->attendee_id;

        $verification = IdentityVerification::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'event_id' => $eventId,
            'attendee_id' => $attendeeId,
            'method' => 'face_capture',
            'status' => IdentityVerificationStatus::PENDING,
            'provider' => 'mock',
            'provider_reference' => 'provider-ref-should-not-leak',
        ]);

        IdentityBiometricArtifact::query()->create([
            'tenant_id' => $scan['fixture']['tenant']->id,
            'verification_id' => $verification->id,
            'artifact_type' => 'template',
            'storage_reference' => '{"ciphertext":"raw-biometric"}',
            'retention_until' => CarbonImmutable::now()->addDays(30),
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->actingAsScanner($scan);

        $review = $this->getJson(
            "/api/v1/tenant/events/{$eventId}/identity/review",
            $this->tenantHeaders($scan['fixture']['tenant']),
        )->assertOk();

        $encoded = json_encode($review->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('raw-biometric', $encoded);
        self::assertStringNotContainsString('ciphertext', $encoded);

        $compliance = $this->getJson(
            "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity/data",
            $this->tenantHeaders($scan['fixture']['tenant']),
        )->assertOk();

        $complianceEncoded = json_encode($compliance->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('raw-biometric', $complianceEncoded);
        self::assertStringNotContainsString('ciphertext', $complianceEncoded);
        self::assertStringNotContainsString('provider-ref-should-not-leak', $complianceEncoded);
        self::assertFalse((bool) config('identity-verification.cross_border_transfer'));
    }

    public function test_public_face_capture_response_excludes_raw_capture_material(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createIdentityAttendeeFixture();
        $eventId = (string) $context['fixture']['event']->id;
        $attendeeId = (string) $context['attendee']->id;
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $context['fixture']['tenant']->id,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            'face_fallback_enabled' => true,
        ]);

        $this->postJson(
            "{$base}/consent",
            ['notice_version' => 'identity-v1', 'residency_mode' => 'on_premise', 'consented' => true],
            $this->identityAttendeeHeaders($context, 'residency-consent-'.Str::ulid()),
        )->assertCreated();

        $response = $this->postJson(
            "{$base}/face-capture",
            ['capture' => 'raw-face-capture-material'],
            $this->identityAttendeeHeaders($context, 'residency-face-'.Str::ulid()),
        )->assertAccepted();

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('raw-face-capture-material', $encoded);
        self::assertStringNotContainsString('ciphertext', $encoded);
    }
}
