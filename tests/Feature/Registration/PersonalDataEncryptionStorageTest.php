<?php

namespace Tests\Feature\Registration;

use App\Modules\Events\Application\Actions\SendPrivateEventInvites;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationOtp;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PersonalDataEncryptionStorageTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    #[Test]
    public function registration_otp_stores_ciphertext_not_plaintext_email_or_payload(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = EventCategory::query()->create([
            'event_id' => $fixture['event']->id,
            'name' => 'General',
            'name_ar' => 'عام',
            'slug' => 'general-'.Str::lower((string) Str::ulid()),
            'color' => '#2563eb',
            'is_paid' => false,
            'price_minor' => 0,
            'currency' => 'SAR',
            'sort_order' => 0,
        ]);
        $fixture['event']->forceFill([
            'slug' => 'otp-encrypt-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $email = 'otp-encrypt@example.test';
        $draft = $this->withHeader('Idempotency-Key', 'otp-encrypt-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Otp', 'last_name' => 'Buyer', 'email' => $email],
                'attendee' => ['first_name' => 'Otp', 'last_name' => 'Attendee', 'email' => $email],
                'answers' => [
                    'full_name' => 'Otp Attendee',
                    'email' => $email,
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $draft->assertCreated();
        $otp = RegistrationOtp::query()->where('token', $draft->json('data.token'))->firstOrFail();
        $guard = app(PersonalDataGuard::class);

        self::assertNull($otp->email);
        self::assertNull($otp->payload);
        self::assertNotNull($otp->email_ciphertext);
        self::assertNotNull($otp->payload_ciphertext);
        self::assertNotNull($otp->encryption_key_id);
        self::assertSame($email, $otp->resolvedEmail($guard));
        self::assertSame($guard->emailIndex($email), $otp->email_index);

        $payload = $otp->resolvedPayload($guard);
        self::assertSame($email, $payload['attendee']['email'] ?? null);
        self::assertStringNotContainsString($email, (string) $otp->email_ciphertext);
    }

    #[Test]
    public function private_invites_store_encrypted_email_and_are_findable_by_blind_index(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $fixture['event']->forceFill([
            'tier' => 'private',
            'status' => 'published',
        ])->save();

        $email = 'invite-encrypt@example.test';
        $result = app(SendPrivateEventInvites::class)->execute(
            $fixture['event'],
            [['email' => $email, 'name' => 'Invitee Name']],
            'en',
        );

        self::assertSame(1, $result['sent']);
        $invite = EventRegistrationInvite::query()->findOrFail($result['invites'][0]['id']);
        $guard = app(PersonalDataGuard::class);

        self::assertNull($invite->email);
        self::assertNull($invite->name);
        self::assertNotNull($invite->email_ciphertext);
        self::assertNotNull($invite->name_ciphertext);
        self::assertSame($email, $invite->resolvedEmail($guard));
        self::assertSame('Invitee Name', $invite->resolvedName($guard));
        self::assertSame($guard->emailIndex($email), $invite->email_index);

        self::assertTrue(
            EventRegistrationInvite::query()
                ->where('event_id', $fixture['event']->id)
                ->where('email_index', $guard->emailIndex($email))
                ->exists(),
        );
    }

    #[Test]
    public function encryption_toggle_off_still_round_trips_via_guard(): void
    {
        config(['credentials.personal_data_encryption_enabled' => false]);
        $this->app->forgetInstance(PersonalDataGuard::class);
        $this->app->forgetInstance(\App\Modules\Shared\Application\DataProtection\PersonalDataCipher::class);
        $this->app->forgetInstance(\App\Modules\Shared\Application\DataProtection\BlindIndex::class);

        $guard = app(PersonalDataGuard::class);
        self::assertFalse($guard->enabled());

        $encrypted = $guard->encryptString('toggle-off@example.test', 'tenant:event:test');
        self::assertSame(PersonalDataGuard::PLAIN_KEY_ID, $encrypted['key_id']);
        self::assertSame('toggle-off@example.test', $guard->decryptString($encrypted, 'tenant:event:test'));
    }
}
