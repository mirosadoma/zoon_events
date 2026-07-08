<?php

namespace Tests\Feature\IdentityVerification;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Application\Actions\IssueCredential;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityVerificationStatus;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerification;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-issuance-enforcement')]
final class IssuanceEnforcementTest extends Phase5MySqlTestCase
{
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_credential_issuance_is_withheld_until_identity_is_verified(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $fixture = $this->createRegistrationFixture();
        $eventId = (string) $fixture['event']->id;

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $eventId,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => false,
        ]);

        $created = $this->withHeader('Idempotency-Key', 'identity-issuance-'.Str::ulid())
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        self::assertSame(0, Credential::query()->where('event_id', $eventId)->count());
        $created->assertJsonMissingPath('data.credential.qr_payload');

        $context = [
            'fixture' => $fixture,
            'attendee' => Attendee::query()
                ->where('event_id', $eventId)->firstOrFail(),
            'accessToken' => (string) $created->json('data.access_token'),
        ];
        $attendeeId = (string) $context['attendee']->id;
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        $this->postJson(
            "{$base}/consent",
            ['notice_version' => 'identity-v1', 'residency_mode' => 'on_premise', 'consented' => true],
            $this->identityAttendeeHeaders($context, 'identity-issuance-consent-'.Str::ulid()),
        )->assertCreated();

        $this->postJson(
            "{$base}/verification",
            [],
            $this->identityAttendeeHeaders($context, 'identity-issuance-start-'.Str::ulid()),
        )->assertAccepted();

        self::assertSame(0, Credential::query()->where('event_id', $eventId)->count());

        $issued = app(IssueCredential::class)->execute(
            (string) $fixture['tenant']->id,
            $eventId,
            $attendeeId,
            (string) $fixture['ticket']->id,
            CarbonImmutable::parse($fixture['event']->end_at),
        );

        self::assertNotNull($issued);
        self::assertSame(1, Credential::query()->where('event_id', $eventId)->where('status', 'active')->count());
        self::assertSame(
            IdentityVerificationStatus::GOV_VERIFIED,
            IdentityVerification::query()->where('attendee_id', $attendeeId)->value('status'),
        );
    }
}
