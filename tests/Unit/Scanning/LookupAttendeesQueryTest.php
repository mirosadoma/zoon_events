<?php

namespace Tests\Unit\Scanning;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Scanning\Application\Queries\LookupAttendeesQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase2MySqlTestCase;

#[Group('manual-desk')]
#[Group('phase-3')]
final class LookupAttendeesQueryTest extends Phase2MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_search_scopes_by_tenant_event_and_returns_decrypted_display_name_for_name_query(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $other = $this->createIssuedCredentialScanFixture();

        $this->renameAttendee($scan, 'Leila', 'Desk');
        $this->renameAttendee($other, 'Leila', 'Outside');

        $result = app(LookupAttendeesQuery::class)->search(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            'leila',
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame('Leila Desk', $result['matches'][0]['display_name']);
    }

    public function test_search_finds_attendee_by_exact_email(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $email = $this->attendeeEmail($scan);

        $result = app(LookupAttendeesQuery::class)->search(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            '  '.mb_strtoupper($email).'  ',
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame((string) $scan['credential']->id, $result['matches'][0]['credential_id']);
    }

    public function test_search_finds_attendee_by_email_fragment(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $email = $this->attendeeEmail($scan);
        $localPart = Str::before($email, '@');

        $result = app(LookupAttendeesQuery::class)->search(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $localPart.'@',
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame((string) $scan['credential']->attendee_id, $result['matches'][0]['attendee_id']);
    }

    public function test_search_finds_attendee_by_order_reference(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $orderId = \App\Modules\Orders\Infrastructure\Persistence\Models\OrderItem::query()
            ->where('tenant_id', $scan['fixture']['tenant']->id)
            ->where('attendee_id', $scan['credential']->attendee_id)
            ->value('order_id');
        self::assertNotNull($orderId);

        $order = \App\Modules\Orders\Infrastructure\Persistence\Models\Order::query()->findOrFail($orderId);

        $result = app(LookupAttendeesQuery::class)->search(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            (string) $order->public_reference,
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame((string) $scan['credential']->attendee_id, $result['matches'][0]['attendee_id']);
        self::assertSame((string) $scan['credential']->id, $result['matches'][0]['credential_id']);
    }

    public function test_search_strips_invisible_characters_from_email_query(): void
    {
        $scan = $this->createIssuedCredentialScanFixture();
        $email = $this->attendeeEmail($scan);
        $dirty = "\u{200B}{$email}\u{200E}";

        $result = app(LookupAttendeesQuery::class)->search(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            $dirty,
            8
        );

        self::assertFalse($result['too_many']);
        self::assertCount(1, $result['matches']);
        self::assertSame((string) $scan['credential']->id, $result['matches'][0]['credential_id']);
    }

    public function test_search_returns_too_many_when_match_count_exceeds_bound(): void
    {
        $fixture = $this->createRegistrationFixture();

        for ($i = 0; $i < 3; $i++) {
            $payload = $this->registrationPayload($fixture);
            $email = "bounded{$i}@example.test";
            $payload['buyer'] = ['first_name' => 'Bounded', 'last_name' => 'Match', 'email' => $email];
            $payload['attendee'] = ['first_name' => 'Bounded', 'last_name' => 'Match', 'email' => $email];
            $payload['answers'] = [
                'full_name' => 'Bounded Match',
                'email' => $email,
                'phone' => '+96650123456'.$i,
            ];

            $this->withHeader('Idempotency-Key', 'lookup-bound-'.$i)->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $payload,
            )->assertCreated();
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

    /** @param array{fixture: array<string, mixed>, credential: \App\Modules\Credentials\Infrastructure\Persistence\Models\Credential} $scan */
    private function attendeeEmail(array $scan): string
    {
        $attendee = Attendee::query()->findOrFail($scan['credential']->attendee_id);
        $email = app(LookupAttendeesQuery::class)->emailDestinationForAttendee(
            $scan['fixture']['tenant']->id,
            $scan['fixture']['event']->id,
            (string) $attendee->id,
        );

        self::assertIsString($email);

        return $email;
    }

    /** @param array{fixture: array<string, mixed>, credential: \App\Modules\Credentials\Infrastructure\Persistence\Models\Credential} $scan */
    private function renameAttendee(array $scan, string $firstName, string $lastName): void
    {
        $tenantId = $scan['fixture']['tenant']->id;
        $eventId = $scan['fixture']['event']->id;
        $attendee = Attendee::query()->findOrFail($scan['credential']->attendee_id);
        $cipher = app(\App\Modules\Shared\Application\DataProtection\PersonalDataCipher::class);
        $scope = "{$tenantId}:{$eventId}:attendee";

        $attendee->forceFill([
            'first_name_ciphertext' => $cipher->encrypt($firstName, $scope)['ciphertext'],
            'last_name_ciphertext' => $cipher->encrypt($lastName, $scope)['ciphertext'],
            'encryption_key_id' => $cipher->encrypt('noop', $scope)['key_id'],
        ])->save();
    }
}
