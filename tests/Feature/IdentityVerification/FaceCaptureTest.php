<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationMethod;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-face-capture')]
final class FaceCaptureTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_face_capture_submit_stores_encrypted_template_and_pending_review_item(): void
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
            $this->identityAttendeeHeaders($context, 'face-consent-'.Str::ulid()),
        )->assertCreated();

        $this->postJson(
            "{$base}/face-capture",
            ['capture' => 'minimized-face-template-ref'],
            $this->identityAttendeeHeaders($context, 'face-submit-'.Str::ulid()),
        )->assertAccepted()
            ->assertJsonPath('data.verification.status', IdentityVerificationStatus::PENDING)
            ->assertJsonPath('data.artifact_type', 'template');

        $verification = IdentityVerification::query()->where('attendee_id', $attendeeId)->firstOrFail();
        self::assertSame(IdentityVerificationMethod::FACE_CAPTURE, $verification->method);
        self::assertSame(IdentityVerificationStatus::PENDING, $verification->status);

        $artifact = IdentityBiometricArtifact::query()->where('verification_id', $verification->id)->firstOrFail();
        self::assertSame('template', $artifact->artifact_type);
        self::assertStringContainsString('ciphertext', $artifact->storage_reference);
        self::assertStringNotContainsString('minimized-face-template-ref', $artifact->storage_reference);
        self::assertNotNull($artifact->retention_until);
    }
}
