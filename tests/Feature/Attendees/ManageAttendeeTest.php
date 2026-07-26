<?php

namespace Tests\Feature\Attendees;

use App\Modules\Attendees\Application\Actions\CorrectAttendee;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\CreatesPhase2ScanFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('attendees')]
final class ManageAttendeeTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use CreatesPhase2ScanFixture;
    use DatabaseTransactions;

    public function test_correction_encrypts_value_and_history_contains_redacted_marker_only(): void
    {
        $fixture = $this->createRegistrationFixture();
        $this->withHeader('Idempotency-Key', 'attendee-correction')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        $attendee = Attendee::query()->where('event_id', $fixture['event']->id)->firstOrFail();
        $context = $this->context($fixture);

        $updated = app(CorrectAttendee::class)->execute(
            $context,
            $fixture['event']->id,
            $attendee->id,
            ['email' => 'corrected@example.test'],
            'Attendee requested correction',
        );
        $plain = app(PersonalDataCipher::class)->decrypt(
            ['key_id' => $updated->encryption_key_id, 'ciphertext' => $updated->email_ciphertext],
            "{$fixture['tenant']->id}:{$fixture['event']->id}:attendee",
        );
        self::assertSame('corrected@example.test', $plain);
        $history = DB::table('attendee_corrections')->where('attendee_id', $attendee->id)->first();
        self::assertStringNotContainsString('corrected@example.test', json_encode($history));
        self::assertStringContainsString('changed', $history->changed_fields);
    }

    public function test_cross_tenant_correction_is_uniform_not_found(): void
    {
        $fixture = $this->createRegistrationFixture();
        $other = Tenant::factory()->create(['created_by_user_id' => $fixture['actor']->id]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $other->id, 'user_id' => $fixture['actor']->id,
            'status' => 'active', 'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->expectException(ModelNotFoundException::class);
        app(CorrectAttendee::class)->execute(
            new TenantContext($other, $membership, $fixture['actor']),
            $fixture['event']->id,
            '01RANDOMATTENDEE0000000000',
            ['email' => 'x@example.test'],
            'Synthetic test',
        );
    }

    public function test_organizer_can_cancel_attendee_via_api(): void
    {
        $this->seed(PermissionSeeder::class);
        $fixture = $this->createRegistrationFixture();
        $this->withHeader('Idempotency-Key', 'attendee-cancel-reg-'.Str::lower((string) Str::ulid()))
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $this->registrationPayload($fixture),
            )->assertCreated();

        $attendee = Attendee::query()->where('event_id', $fixture['event']->id)->firstOrFail();
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->grantTenantPermissions($fixture['tenant'], $membership, ['attendee.manage']);
        $this->actingAsTenantMember($fixture['actor'], $fixture['tenant']);

        $this->deleteJson(
            "/api/v1/tenant/events/{$fixture['event']->id}/attendees/{$attendee->id}",
            [],
            array_merge($this->tenantHeaders($fixture['tenant']), [
                'Idempotency-Key' => 'attendee-cancel-'.Str::lower((string) Str::ulid()),
            ]),
        )->assertOk()
            ->assertJsonPath('data.registration_status', 'cancelled');

        self::assertSame('cancelled', $attendee->fresh()->registration_status);
    }

    public function test_organizer_can_deactivate_invite_via_api(): void
    {
        $this->seed(PermissionSeeder::class);
        $fixture = $this->createRegistrationFixture();
        $invite = EventRegistrationInvite::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'event_id' => $fixture['event']->id,
            'email' => 'invitee@example.test',
            'name' => 'Invitee',
            'code' => '1234567890',
            'is_active' => true,
            'invite_status' => 'not_registered',
            'sent_at' => now(),
        ]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);
        $this->grantTenantPermissions($fixture['tenant'], $membership, ['event.invite.manage']);
        $this->actingAsTenantMember($fixture['actor'], $fixture['tenant']);

        $this->deleteJson(
            "/api/v1/tenant/events/{$fixture['event']->id}/invites/{$invite->id}",
            [],
            array_merge($this->tenantHeaders($fixture['tenant']), [
                'Idempotency-Key' => 'invite-deactivate-'.Str::lower((string) Str::ulid()),
            ]),
        )->assertOk()
            ->assertJsonPath('data.is_active', false);

        self::assertFalse((bool) $invite->fresh()->is_active);
    }

    private function context(array $fixture): TenantContext
    {
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id,
            'user_id' => $fixture['actor']->id,
            'status' => 'active',
            'created_by_user_id' => $fixture['actor']->id,
        ]);

        return new TenantContext($fixture['tenant'], $membership, $fixture['actor']);
    }
}
