<?php

namespace Tests\Feature\Events;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\IdentityVerification\Domain\ValueObjects\IdentityRequirementLevel;
use App\Modules\IdentityVerification\Infrastructure\Persistence\Models\IdentityVerificationRequirement;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
final class PublicEventRegistrationSubmitTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_public_web_registration_returns_credential_qr_when_identity_gate_allows_issuance(): void
    {
        $fixture = $this->createRegistrationFixture();
        $fixture['event']->forceFill([
            'slug' => 'summit-web-'.Str::lower((string) Str::ulid()),
            'status' => 'published',
        ])->save();

        $response = $this->withHeader('Idempotency-Key', 'web-reg-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'ticket_type_id' => (string) $fixture['ticket']->id,
                'buyer' => ['first_name' => 'Web', 'last_name' => 'Buyer', 'email' => 'web-buyer@example.test'],
                'attendee' => ['first_name' => 'Web', 'last_name' => 'Attendee', 'email' => 'web-attendee@example.test'],
                'answers' => [
                    'full_name' => 'Web Attendee',
                    'email' => 'web-attendee@example.test',
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.credential_status', 'issued')
            ->assertJsonPath('data.credential.qr_payload', fn ($value): bool => is_string($value) && str_starts_with($value, 'ord_'))
            ->assertJsonPath('data.public_reference', fn ($value): bool => is_string($value) && str_starts_with($value, 'ord_'));

        self::assertSame(1, Credential::query()->where('event_id', $fixture['event']->id)->count());
        self::assertSame(1, Notification::query()->where('event_id', $fixture['event']->id)->where('channel', 'email')->count());
    }

    public function test_public_web_registration_sends_confirmation_email_even_when_credential_is_withheld(): void
    {
        $fixture = $this->createRegistrationFixture();

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

        $response = $this->withHeader('Idempotency-Key', 'web-reg-gated-'.Str::ulid())
            ->postJson("/en/events/{$fixture['event']->slug}/register", [
                'form_version_id' => (string) $fixture['form']->id,
                'ticket_type_id' => (string) $fixture['ticket']->id,
                'buyer' => ['first_name' => 'Gated', 'last_name' => 'Buyer', 'email' => 'gated-buyer@example.test'],
                'attendee' => ['first_name' => 'Gated', 'last_name' => 'Attendee', 'email' => 'gated-attendee@example.test'],
                'answers' => [
                    'full_name' => 'Gated Attendee',
                    'email' => 'gated-attendee@example.test',
                    'phone' => '+966501234567',
                ],
                'consents' => ['terms' => true, 'privacy' => true, 'marketing' => false],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.credential_status', 'pending_identity')
            ->assertJsonMissingPath('data.credential.qr_payload');

        self::assertSame(0, Credential::query()->where('event_id', $fixture['event']->id)->count());
        self::assertSame(1, Notification::query()->where('event_id', $fixture['event']->id)->where('channel', 'email')->count());
        self::assertSame(1, Attendee::query()->where('event_id', $fixture['event']->id)->count());
    }
}
