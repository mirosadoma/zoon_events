<?php

namespace Tests\Feature\Events;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Registration\Domain\Fields\RegistrationSystemFields;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationOtp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventRegistrationSubmitTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_public_web_registration_starts_otp_flow_with_category(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);
        $fields = [
            ...RegistrationSystemFields::definitions(),
            [
                'key' => 'company',
                'type' => 'text',
                'label_en' => 'Company',
                'label_ar' => 'الشركة',
                'required' => true,
                'visibility' => 'public',
            ],
        ];
        $form = RegistrationFormVersion::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'registration_form_id' => $fixture['form']->registration_form_id,
            'version' => 2,
            'status' => 'published',
            'fields' => $fields,
            'schema_hash' => hash('sha256', json_encode($fields)),
            'privacy_notice_version' => 'privacy-v1',
            'terms_version' => 'terms-v1',
            'published_by_user_id' => $fixture['actor']->id,
            'published_at' => now(),
        ]);
        $fixture['event']->forceFill([
            'slug' => 'summit-company-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
            'active_form_version_id' => $form->id,
        ])->save();

        $response = $this->withHeader('Idempotency-Key', 'web-reg-company-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $form->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Amr', 'last_name' => 'Sadoma', 'email' => 'amr@example.test', 'phone' => '01276069689'],
                'attendee' => ['first_name' => 'Amr', 'last_name' => 'Sadoma', 'email' => 'amr@example.test', 'phone' => '01276069689'],
                'answers' => [
                    'full_name' => 'Amr Sadoma',
                    'email' => 'amr@example.test',
                    'phone' => '01276069689',
                    'company' => 'sadoma',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.next', 'otp')
            ->assertJsonPath('data.otp_url', fn ($value): bool => is_string($value) && str_contains($value, '/register/otp/'));

        self::assertSame(1, RegistrationOtp::query()->where('event_id', $fixture['event']->id)->count());
    }

    public function test_public_web_registration_otp_completes_free_category(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);
        $fixture['event']->forceFill([
            'slug' => 'summit-web-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $draft = $this->withHeader('Idempotency-Key', 'web-reg-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Web', 'last_name' => 'Buyer', 'email' => 'web-buyer@example.test'],
                'attendee' => ['first_name' => 'Web', 'last_name' => 'Attendee', 'email' => 'web-attendee@example.test'],
                'answers' => [
                    'full_name' => 'Web Attendee',
                    'email' => 'web-attendee@example.test',
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $draft->assertCreated();
        $token = (string) $draft->json('data.token');
        $otp = RegistrationOtp::query()->where('token', $token)->firstOrFail();
        $code = '123456';
        $otp->forceFill(['code_hash' => hash('sha256', $code)])->save();

        $response = $this->postJson("/en/events/{$fixture['event']->slug}/register/otp/{$token}", [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.next', 'confirmation')
            ->assertJsonPath('data.public_reference', fn ($value): bool => is_string($value) && str_starts_with($value, 'ord_'));

        self::assertSame(1, Credential::query()->where('event_id', $fixture['event']->id)->count());
        self::assertSame(1, Notification::query()->where('event_id', $fixture['event']->id)->where('channel', 'email')->count());
    }

    public function test_public_web_registration_sends_confirmation_email_even_when_credential_is_withheld(): void
    {
        Mail::fake();
        $fixture = $this->createRegistrationFixture();
        $category = $this->createFreeCategory($fixture);

        IdentityVerificationRequirement::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'ticket_type_id' => null,
            'level' => IdentityRequirementLevel::REQUIRED_BEFORE_CREDENTIAL,
            'face_fallback_enabled' => false,
        ]);

        $fixture['event']->forceFill([
            'slug' => 'identity-gated-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $draft = $this->withHeader('Idempotency-Key', 'web-reg-gated-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'event_category_id' => $category->id,
                'buyer' => ['first_name' => 'Gated', 'last_name' => 'Buyer', 'email' => 'gated-buyer@example.test'],
                'attendee' => ['first_name' => 'Gated', 'last_name' => 'Attendee', 'email' => 'gated-attendee@example.test'],
                'answers' => [
                    'full_name' => 'Gated Attendee',
                    'email' => 'gated-attendee@example.test',
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $draft->assertCreated();
        $token = (string) $draft->json('data.token');
        $otp = RegistrationOtp::query()->where('token', $token)->firstOrFail();
        $code = '654321';
        $otp->forceFill(['code_hash' => hash('sha256', $code)])->save();

        $response = $this->postJson("/en/events/{$fixture['event']->slug}/register/otp/{$token}", [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.next', 'confirmation');

        self::assertSame(0, Credential::query()->where('event_id', $fixture['event']->id)->count());
        self::assertSame(1, Notification::query()->where('event_id', $fixture['event']->id)->where('channel', 'email')->count());
        self::assertSame(1, Attendee::query()->where('event_id', $fixture['event']->id)->count());
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
