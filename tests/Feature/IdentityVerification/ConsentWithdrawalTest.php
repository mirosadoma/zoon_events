<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityBiometricArtifact;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-consent-withdrawal')]
final class ConsentWithdrawalTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_withdraw_consent_removes_sensitive_data_reverts_status_and_blocks_capture_until_reconsent(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createIdentityAttendeeFixture();
        $tenantId = (string) $context['fixture']['tenant']->id;
        $eventId = (string) $context['fixture']['event']->id;
        $attendeeId = (string) $context['attendee']->id;
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_GATE,
            'face_fallback_enabled' => true,
        ]);

        $this->postJson(
            "{$base}/consent",
            [
                'notice_version' => 'identity-v1',
                'residency_mode' => 'on_premise',
                'consented' => true,
            ],
            $this->identityAttendeeHeaders($context, 'identity-consent-'.Str::ulid()),
        )->assertCreated();

        $started = $this->postJson(
            "{$base}/verification",
            [],
            $this->identityAttendeeHeaders($context, 'identity-start-'.Str::ulid()),
        )->assertAccepted();

        $reference = (string) $started->json('data.provider_reference');
        $this->signedGovernmentCallback([
            'reference' => $reference,
            'status' => 'verified',
        ])->assertOk();

        $verification = IdentityVerification::query()->firstOrFail();
        IdentityBiometricArtifact::query()->create([
            'tenant_id' => $tenantId,
            'verification_id' => $verification->id,
            'artifact_type' => 'template',
            'storage_reference' => '{"ciphertext":"secret-template"}',
            'liveness_result' => 'passed',
            'retention_until' => CarbonImmutable::now()->addDays(30),
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->deleteJson(
            "{$base}/consent",
            [],
            $this->identityAttendeeHeaders($context, 'identity-withdraw-'.Str::ulid()),
        )->assertOk()
            ->assertJsonPath('data.withdrawn', true)
            ->assertJsonPath('data.status', IdentityVerificationStatus::PENDING);

        $consent = IdentityConsent::query()->firstOrFail();
        self::assertNotNull($consent->withdrawn_at);

        $verification->refresh();
        self::assertSame(IdentityVerificationStatus::PENDING, $verification->status);
        self::assertNull($verification->consent_id);
        self::assertNull($verification->verified_name);
        self::assertNull($verification->provider_reference);

        $artifact = IdentityBiometricArtifact::query()->firstOrFail();
        self::assertSame('purged', $artifact->storage_reference);
        self::assertNotNull($artifact->purged_at);

        self::assertTrue(
            AuditLog::query()->where('action', 'identity_consent.withdrawn')->exists(),
        );

        $this->assertProblemDetails(
            $this->postJson(
                "{$base}/verification",
                [],
                $this->identityAttendeeHeaders($context, 'identity-after-withdraw-'.Str::ulid()),
            ),
            409,
            'identity_consent_missing',
        );
    }
}
