<?php

namespace Tests\Feature\IdentityVerification;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsProblemDetails;
use Tests\Support\CreatesIdentityAttendeeFixture;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase5MySqlTestCase;

#[Group('phase-5')]
#[Group('identity-consent-guard')]
final class ConsentGuardTest extends Phase5MySqlTestCase
{
    use AssertsProblemDetails;
    use CreatesIdentityAttendeeFixture;
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_verification_start_returns_identity_consent_missing_without_consent(): void
    {
        $this->assertMySqlConnectionIsAvailable();
        $context = $this->createIdentityAttendeeFixture();
        $eventId = (string) $context['fixture']['event']->id;
        $attendeeId = (string) $context['attendee']->id;
        $base = "/api/v1/tenant/events/{$eventId}/attendees/{$attendeeId}/identity";

        $this->assertProblemDetails(
            $this->postJson(
                "{$base}/verification",
                [],
                $this->identityAttendeeHeaders($context, 'identity-no-consent-'.Str::ulid()),
            ),
            409,
            'identity_consent_missing',
        );
    }
}
