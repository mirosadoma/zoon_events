<?php

namespace Tests\Integration\Registration;

use App\Models\User;
use App\Modules\Audit\Contracts\AuditWriter;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('free-registration')]
final class FreeRegistrationTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_free_registration_is_atomic_and_replay_returns_original_safe_order(): void
    {
        $fixture = $this->createRegistrationFixture();
        $url = "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations";
        $headers = ['Idempotency-Key' => 'free-registration-attempt-1'];

        $created = $this->withHeaders($headers)->postJson($url, $this->registrationPayload($fixture));
        $created->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_status', 'not_required')
            ->assertJsonPath('data.total_minor', 0);
        self::assertIsString($created->json('data.access_token'));
        self::assertIsString($created->json('data.credential.qr_payload'));

        $replayed = $this->withHeaders($headers)->postJson($url, $this->registrationPayload($fixture));
        $replayed->assertOk()->assertJsonMissingPath('data.access_token')->assertJsonMissingPath('data.credential.qr_payload');
        self::assertSame($created->json('data.public_reference'), $replayed->json('data.public_reference'));

        foreach (['registration_submissions', 'orders', 'order_items', 'attendees', 'credentials', 'notifications'] as $table) {
            self::assertSame(1, DB::table($table)->where('tenant_id', $fixture['tenant']->id)->count(), $table);
        }
        self::assertSame(1, DB::table('ticket_inventories')->where('ticket_type_id', $fixture['ticket']->id)->value('sold_quantity'));
        self::assertTrue(DB::table('audit_logs')->where('tenant_id', $fixture['tenant']->id)->where('action', 'registration.free_completed')->exists());
    }

    public function test_invalid_submission_leaves_no_partial_aggregate_or_inventory_change(): void
    {
        $fixture = $this->createRegistrationFixture();
        $payload = $this->registrationPayload($fixture);
        $payload['answers'] = ['forged_internal_field' => 'x'];

        $this->withHeader('Idempotency-Key', 'invalid-attempt')
            ->postJson("http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations", $payload)
            ->assertUnprocessable();

        self::assertSame(0, DB::table('orders')->where('tenant_id', $fixture['tenant']->id)->count());
        self::assertSame(0, DB::table('ticket_inventories')->where('ticket_type_id', $fixture['ticket']->id)->value('held_quantity'));
    }

    public function test_forced_required_audit_failure_rolls_back_entire_aggregate(): void
    {
        $fixture = $this->createRegistrationFixture();
        app()->instance(AuditWriter::class, new class implements AuditWriter
        {
            public function write(
                string $scope,
                ?string $tenantId,
                string $action,
                string $outcome,
                ?User $actor = null,
                ?string $reasonCode = null,
                ?string $targetType = null,
                ?string $targetId = null,
                array $metadata = [],
                ?array $changeSummary = null,
            ): AuditLog {
                throw new \RuntimeException('Synthetic audit failure.');
            }
        });

        $this->withHeader('Idempotency-Key', 'audit-failure')
            ->postJson(
                "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
                $this->registrationPayload($fixture),
            )
            ->assertServiceUnavailable();

        foreach (['registration_submissions', 'orders', 'order_items', 'attendees', 'credentials', 'notifications'] as $table) {
            self::assertSame(0, DB::table($table)->where('tenant_id', $fixture['tenant']->id)->count(), $table);
        }
        self::assertSame(0, DB::table('ticket_inventories')->where('ticket_type_id', $fixture['ticket']->id)->value('held_quantity'));
        self::assertSame(0, DB::table('ticket_inventories')->where('ticket_type_id', $fixture['ticket']->id)->value('sold_quantity'));
    }
}
