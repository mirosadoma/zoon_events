<?php

namespace Tests\Unit\Scanning;

use App\Modules\Scanning\Application\Queries\LookupAttendeesQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('manual-desk')]
#[Group('phase-3')]
final class LookupAttendeesQueryTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_search_scopes_by_tenant_event_and_returns_decrypted_display_name_for_name_query(): void
    {
        $fixture = $this->createRegistrationFixture();
        $otherFixture = $this->createRegistrationFixture();

        $this->createAttendeeThroughRegistration($fixture, 'Leila', 'Desk', 'leila.desk@example.test', 'lookup-1');
        $this->createAttendeeThroughRegistration($otherFixture, 'Leila', 'Outside', 'leila.outside@example.test', 'lookup-2');

        $result = app(LookupAttendeesQuery::class)->search(
            $fixture['tenant']->id,
            $fixture['event']->id,
            'leila',
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame('Leila Desk', $result['matches'][0]['display_name']);
    }

    public function test_search_returns_too_many_when_match_count_exceeds_bound(): void
    {
        $fixture = $this->createRegistrationFixture();

        for ($i = 0; $i < 3; $i++) {
            $this->createAttendeeThroughRegistration(
                $fixture,
                'Bounded',
                'Match',
                "bounded{$i}@example.test",
                "lookup-bound-{$i}"
            );
        }

        $result = app(LookupAttendeesQuery::class)->search(
            $fixture['tenant']->id,
            $fixture['event']->id,
            'bounded',
            2
        );

        self::assertTrue($result['too_many']);
        self::assertSame([], $result['matches']);
    }

    /** @param array{actor:\App\Models\User,tenant:\App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant,event:\App\Modules\Events\Infrastructure\Persistence\Models\Event,form:\App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion,ticket:\App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType} $fixture */
    private function createAttendeeThroughRegistration(array $fixture, string $firstName, string $lastName, string $email, string $idempotencyKey): void
    {
        $payload = $this->registrationPayload($fixture);
        $payload['buyer'] = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
        $payload['attendee'] = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];
        $payload['answers'] = ['email' => $email];

        $this->withHeader('Idempotency-Key', $idempotencyKey)->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $payload,
        )->assertCreated();
    }
}
