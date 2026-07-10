<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-gov-callback')]
final class GovCallbackTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_signed_government_callback_is_processed_idempotently(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createIdentityAttendeeFixture();
        $eventId = (string) $context['fixture']['event']->id;
        $attendeeId = (string) $context['attendee']->id;
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        $this->postJson(
            "{$base}/consent",
            [
                'notice_version' => 'identity-v1',
                'residency_mode' => 'on_premise',
                'consented' => true,
            ],
            $this->identityAttendeeHeaders($context, 'identity-callback-consent-'.Str::ulid()),
        )->assertCreated();

        $reference = (string) $this->postJson(
            "{$base}/verification",
            [],
            $this->identityAttendeeHeaders($context, 'identity-callback-start-'.Str::ulid()),
        )->assertAccepted()->json('data.provider_reference');

        $payload = ['reference' => $reference, 'status' => 'verified'];

        $this->signedGovernmentCallback($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.status', IdentityVerificationStatus::GOV_VERIFIED);

        $verification = IdentityVerification::query()->firstOrFail();
        $verifiedAt = $verification->verified_at?->toIso8601String();
        $resultAudits = AuditLog::query()
            ->where('action', 'identity_verification.result_recorded')
            ->count();

        $this->signedGovernmentCallback($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.status', IdentityVerificationStatus::GOV_VERIFIED);

        $verification->refresh();
        self::assertSame($verifiedAt, $verification->verified_at?->toIso8601String());
        self::assertSame(
            $resultAudits,
            AuditLog::query()->where('action', 'identity_verification.result_recorded')->count(),
        );
    }

    public function test_unsigned_callback_is_rejected(): void
    {
        $this->assertMySqlConnectionIsAvailable();

        $this->postJson('/api/v1/identity/providers/government/callback', [
            'reference' => 'gov-missing',
            'status' => 'verified',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'identity_callback_invalid');
    }
}
