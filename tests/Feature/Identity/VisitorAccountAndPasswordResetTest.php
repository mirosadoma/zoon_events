<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Identity\Infrastructure\Persistence\Models\PasswordResetOtp;
use App\Modules\Identity\Mail\VisitorAccountReminderMail;
use App\Modules\Identity\Mail\VisitorCredentialsMail;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationOtp;
use App\Modules\Shared\Domain\LifecycleStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class VisitorAccountAndPasswordResetTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_duplicate_email_on_same_event_is_rejected(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);
        $fixture['event']->forceFill([
            'slug' => 'dup-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $email = 'dup-attendee@example.test';
        $this->completeFreeRegistration($fixture, $category, $email);

        $second = $this->withHeader('Idempotency-Key', 'dup-2-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Dup', 'last_name' => 'Two', 'email' => $email],
                'attendee' => ['first_name' => 'Dup', 'last_name' => 'Two', 'email' => $email],
                'answers' => [
                    'full_name' => 'Dup Two',
                    'email' => $email,
                    'phone' => '+966501234568',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $second->assertStatus(422)
            ->assertJsonValidationErrors(['attendee.email', 'answers.email']);
    }

    public function test_new_registration_creates_visitor_and_sends_credentials_mail(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);
        $fixture['event']->forceFill([
            'slug' => 'visitor-new-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $email = 'brand-new-visitor@example.test';
        $this->completeFreeRegistration($fixture, $category, $email);

        $user = User::query()->where('email', $email)->first();
        self::assertNotNull($user);
        self::assertSame('visitor', $user->type);
        self::assertSame(1, Attendee::query()->where('event_id', $fixture['event']->id)->where('user_id', $user->id)->count());

        Mail::assertSent(VisitorCredentialsMail::class, fn (VisitorCredentialsMail $mail): bool => $mail->hasTo($email));
    }

    public function test_existing_user_gets_reminder_mail_without_new_account(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);
        $fixture['event']->forceFill([
            'slug' => 'visitor-exist-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $email = 'existing-staff@example.test';
        $existing = User::factory()->create([
            'email' => $email,
            'type' => 'staff',
            'password' => Hash::make('KeepMe123!'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->completeFreeRegistration($fixture, $category, $email);

        self::assertSame(1, User::query()->where('email', $email)->count());
        self::assertSame('staff', $existing->fresh()->type);
        self::assertTrue(Hash::check('KeepMe123!', $existing->fresh()->password));
        self::assertSame(1, Attendee::query()->where('event_id', $fixture['event']->id)->where('user_id', $existing->id)->count());

        Mail::assertSent(VisitorAccountReminderMail::class, fn (VisitorAccountReminderMail $mail): bool => $mail->hasTo($email));
        Mail::assertNotSent(VisitorCredentialsMail::class);
    }

    public function test_visitor_login_redirects_to_visitor_portal(): void
    {
        $user = User::factory()->create([
            'email' => 'portal-visitor@example.test',
            'type' => 'visitor',
            'password' => Hash::make('ZON@123'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->from('/en/login')
            ->post('/en/login', [
                'email' => $user->email,
                'password' => 'ZON@123',
            ])
            ->assertRedirect('/en/visitor');
    }

    public function test_visitor_cannot_open_tenant_dashboard(): void
    {
        $user = User::factory()->create([
            'type' => 'visitor',
            'password' => Hash::make('ZON@123'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->actingAs($user)
            ->get('/en/dashboard')
            ->assertRedirect('/en/visitor');
    }

    public function test_staff_cannot_open_visitor_portal(): void
    {
        $user = User::factory()->create([
            'type' => 'staff',
            'password' => Hash::make('password'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->actingAs($user)
            ->get('/en/visitor')
            ->assertForbidden();
    }

    public function test_forgot_password_otp_flow_resets_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-me@example.test',
            'password' => Hash::make('old-pass'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->post('/en/forgot-password', ['email' => $user->email])
            ->assertRedirect();

        $otp = PasswordResetOtp::query()->where('email', $user->email)->firstOrFail();
        $code = '123456';
        $otp->forceFill(['code_hash' => hash('sha256', $code)])->save();

        $this->post("/en/forgot-password/otp/{$otp->token}", ['code' => $code])
            ->assertRedirect();

        $otp->refresh();
        self::assertNotNull($otp->reset_token);

        $this->post("/en/forgot-password/reset/{$otp->reset_token}", [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertRedirect('/en/login');

        self::assertTrue(Hash::check('NewPass123!', $user->fresh()->password));
    }

    public function test_forgot_password_rejects_wrong_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong-otp@example.test',
            'password' => Hash::make('old-pass'),
            'status' => LifecycleStatus::Active->value,
        ]);

        $this->post('/en/forgot-password', ['email' => $user->email])->assertRedirect();
        $otp = PasswordResetOtp::query()->where('email', $user->email)->firstOrFail();

        $this->from("/en/forgot-password/otp/{$otp->token}")
            ->post("/en/forgot-password/otp/{$otp->token}", ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    /** @param array{actor:User,tenant:\App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant,event:Event,form:\App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion,ticket:\App\Modules\Ticketing\Infrastructure\Persistence\Models\TicketType} $fixture */
    private function completeFreeRegistration(array $fixture, EventCategory $category, string $email): void
    {
        $draft = $this->withHeader('Idempotency-Key', 'reg-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Web', 'last_name' => 'Buyer', 'email' => $email],
                'attendee' => ['first_name' => 'Web', 'last_name' => 'Attendee', 'email' => $email],
                'answers' => [
                    'full_name' => 'Web Attendee',
                    'email' => $email,
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $draft->assertCreated();
        $token = (string) $draft->json('data.token');
        $otp = RegistrationOtp::query()->where('token', $token)->firstOrFail();
        $code = '123456';
        $otp->forceFill(['code_hash' => hash('sha256', $code)])->save();

        $this->postJson("/en/events/{$fixture['event']->slug}/register/otp/{$token}", [
            'code' => $code,
        ])->assertOk();
    }

    /** @param array{event:Event} $fixture */
    private function createFreeCategory(array $fixture): EventCategory
    {
        return EventCategory::query()->create([
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
    }
}
