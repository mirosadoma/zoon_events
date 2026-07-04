<?php

namespace Tests\Feature\Attendees;

use App\Modules\Attendees\Application\Actions\CorrectAttendee;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('attendees')]
final class ManageAttendeeTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
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
