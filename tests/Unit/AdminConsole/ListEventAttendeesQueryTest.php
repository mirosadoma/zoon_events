<?php

namespace Tests\Unit\AdminConsole;

use App\Modules\AdminConsole\Application\Queries\ListEventAttendeesQuery;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('admin-dashboard')]
final class ListEventAttendeesQueryTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_public_tab_excludes_attendees_who_registered_via_private_invite(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];
        $event->forceFill(['tier' => 'both'])->save();

        $this->withHeader('Idempotency-Key', 'public-reg-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$event->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $privatePayload = $this->registrationPayload($fixture);
        $privatePayload['attendee']['email'] = 'private.guest@example.test';
        $privatePayload['attendee']['first_name'] = 'Private';
        $privatePayload['attendee']['last_name'] = 'Guest';
        $privatePayload['answers']['email'] = 'private.guest@example.test';
        $privatePayload['answers']['full_name'] = 'Private Guest';
        $privatePayload['buyer']['email'] = 'private.buyer@example.test';

        $this->withHeader('Idempotency-Key', 'private-reg-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$event->slug}/registrations",
                $privatePayload,
            )->assertCreated();

        EventRegistrationInvite::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $event->id,
            'email' => 'private.guest@example.test',
            'name' => 'Private Guest',
            'code' => '1234567890',
            'is_active' => false,
            'invite_status' => 'registered',
            'sent_at' => now()->subHour(),
            'used_at' => now(),
        ]);

        $query = app(ListEventAttendeesQuery::class);
        $public = $query->paginate(
            (string) $fixture['tenant']->id,
            (string) $event->id,
            null,
            null,
            1,
            'public',
        );

        self::assertSame(1, $public['total']);
        self::assertCount(1, $public['attendees']);

        $publicEmails = $public['attendees']->map(
            fn (Attendee $attendee): string => (string) $attendee->email_index,
        )->all();

        $indexes = app(\App\Modules\Shared\Application\DataProtection\BlindIndex::class);
        self::assertContains($indexes->email('attendee@example.test'), $publicEmails);
        self::assertNotContains($indexes->email('private.guest@example.test'), $publicEmails);

        $all = $query->paginate(
            (string) $fixture['tenant']->id,
            (string) $event->id,
            null,
            null,
            1,
            null,
        );
        self::assertSame(2, $all['total']);
    }

    public function test_cancelled_attendees_are_excluded_from_list(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];

        $this->withHeader('Idempotency-Key', 'cancel-list-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$event->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $attendee = Attendee::query()->where('event_id', $event->id)->firstOrFail();
        $attendee->forceFill([
            'registration_status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        $result = app(ListEventAttendeesQuery::class)->paginate(
            (string) $fixture['tenant']->id,
            (string) $event->id,
            null,
            null,
            1,
            'public',
        );

        self::assertSame(0, $result['total']);
        self::assertCount(0, $result['attendees']);
    }

    public function test_filters_attendees_by_event_venue_id(): void
    {
        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];

        $this->withHeader('Idempotency-Key', 'venue-a-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$event->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $second = $this->registrationPayload($fixture);
        $second['attendee']['email'] = 'second@example.test';
        $second['attendee']['first_name'] = 'Second';
        $second['answers']['email'] = 'second@example.test';
        $second['answers']['full_name'] = 'Second Attendee';
        $second['buyer']['email'] = 'second.buyer@example.test';

        $this->withHeader('Idempotency-Key', 'venue-b-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$event->slug}/registrations",
                $second,
            )->assertCreated();

        $attendees = Attendee::query()->where('event_id', $event->id)->orderBy('id')->get();
        self::assertCount(2, $attendees);

        $attendees[0]->forceFill(['event_venue_id' => 101])->save();
        $attendees[1]->forceFill(['event_venue_id' => 202])->save();

        $filtered = app(ListEventAttendeesQuery::class)->paginate(
            (string) $fixture['tenant']->id,
            (string) $event->id,
            null,
            null,
            1,
            'public',
            '101',
        );

        self::assertSame(1, $filtered['total']);
        self::assertCount(1, $filtered['attendees']);
        self::assertSame(101, (int) $filtered['attendees']->first()->event_venue_id);
    }
}
