<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityConsent;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-gov-verification')]
final class GovVerificationTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_consent_decline_stores_nothing_and_success_path_records_gov_verified_attributes(): void
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
            [
                'notice_version' => 'identity-v1',
                'residency_mode' => 'on_premise',
                'consented' => false,
            ],
            $this->identityAttendeeHeaders($context, 'identity-consent-decline-'.Str::ulid()),
        )->assertOk()
            ->assertJsonPath('data.consented', false)
            ->assertJsonPath('data.status', IdentityVerificationStatus::PENDING);

        self::assertSame(0, IdentityConsent::query()->count());
        self::assertSame(0, IdentityVerification::query()->count());

        $this->postJson(
            "{$base}/consent",
            [
                'notice_version' => 'identity-v1',
                'residency_mode' => 'on_premise',
                'consented' => true,
            ],
            $this->identityAttendeeHeaders($context, 'identity-consent-accept-'.Str::ulid()),
        )->assertCreated()
            ->assertJsonPath('data.consented', true);

        self::assertSame(1, IdentityConsent::query()->count());

        $started = $this->postJson(
            "{$base}/verification",
            [],
            $this->identityAttendeeHeaders($context, 'identity-start-'.Str::ulid()),
        )->assertAccepted();

        $reference = (string) $started->json('data.provider_reference');
        self::assertNotSame('', $reference);

        $this->signedGovernmentCallback([
            'reference' => $reference,
            'status' => 'verified',
        ])->assertOk()
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.status', IdentityVerificationStatus::GOV_VERIFIED);

        $this->withHeader('X-Order-Access-Token', $context['accessToken'])
            ->getJson("{$base}/verification")
            ->assertOk()
            ->assertJsonPath('data.status', IdentityVerificationStatus::GOV_VERIFIED)
            ->assertJsonPath('data.verified_name', 'Mock Verified Attendee')
            ->assertJsonPath('data.verified_nationality', 'SA');

        $verification = IdentityVerification::query()->firstOrFail();
        self::assertNotNull($verification->verified_at);
        self::assertNotNull($verification->consent_id);
    }
}
