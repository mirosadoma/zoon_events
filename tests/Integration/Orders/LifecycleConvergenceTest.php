<?php

namespace Tests\Integration\Orders;

use App\Exceptions\FoundationException;
use App\Modules\Events\Application\Actions\ArchiveEvent;
use App\Modules\Events\Application\Actions\ReopenEvent;
use App\Modules\Orders\Application\Actions\CancelOrder;
use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('lifecycle')]
final class LifecycleConvergenceTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_event_reopen_and_archive_require_explicit_reason_and_are_audited(): void
    {
        $fixture = $this->createRegistrationFixture();
        $context = $this->context($fixture);
        $fixture['event']->forceFill(['status' => 'registration_closed'])->save();

        app(ReopenEvent::class)->execute($context, $fixture['event'], 'Extend registration');
        self::assertSame('registration_open', $fixture['event']->refresh()->status);

        $fixture['event']->forceFill(['status' => 'completed'])->save();
        app(ArchiveEvent::class)->execute($context, $fixture['event'], 'Event completed');
        self::assertSame('archived', $fixture['event']->refresh()->status);
        self::assertSame(2, DB::table('audit_logs')->where('target_id', $fixture['event']->id)
            ->whereIn('action', ['event.reopened', 'event.archived'])->count());
    }

    public function test_eligible_unpaid_order_cancellation_releases_inventory_and_is_idempotent(): void
    {
        $fixture = $this->createRegistrationFixture(500);
        $response = $this->withHeader('Idempotency-Key', 'cancel-start')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated()->assertJsonPath('data.status', 'pending_payment');
        $order = Order::query()->where('public_reference', $response->json('data.public_reference'))->firstOrFail();
        $context = $this->context($fixture);

        app(CancelOrder::class)->execute($context, $fixture['event']->id, $order->id, 'Buyer request');
        app(CancelOrder::class)->execute($context, $fixture['event']->id, $order->id, 'Replay');

        self::assertSame('cancelled', $order->refresh()->status);
        self::assertSame('released', DB::table('inventory_holds')->where('id', $order->inventory_hold_id)->value('status'));
        self::assertSame(0, DB::table('ticket_inventories')->where('ticket_type_id', $fixture['ticket']->id)->value('held_quantity'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'order.cancelled')->where('target_id', $order->id)->count());
    }

    public function test_unsettled_payment_blocks_cancellation_and_preserves_hold(): void
    {
        $fixture = $this->createRegistrationFixture(500);
        $response = $this->withHeader('Idempotency-Key', 'guarded-cancel-start')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        $order = Order::query()->where('public_reference', $response->json('data.public_reference'))->firstOrFail();
        $account = PaymentAccount::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'adapter_key' => 'fake',
            'secret_reference' => 'unused', 'account_reference' => 'fake',
            'webhook_route_token_hash' => hash('sha256', 'cancel-route'), 'mode' => 'test',
            'currency' => 'SAR', 'status' => 'active',
        ]);
        PaymentAttempt::query()->create([
            'tenant_id' => $order->tenant_id, 'event_id' => $order->event_id,
            'order_id' => $order->id, 'payment_account_id' => $account->id,
            'attempt_number' => 1, 'idempotency_key_hash' => hash('sha256', 'cancel-attempt'),
            'status' => 'unknown', 'requested_minor' => $order->total_minor,
            'captured_minor' => 0, 'refunded_minor' => 0, 'currency' => $order->currency,
        ]);

        try {
            app(CancelOrder::class)->execute($this->context($fixture), $fixture['event']->id, $order->id, 'Unsafe request');
            self::fail('Unknown payment outcome must block cancellation.');
        } catch (FoundationException $exception) {
            self::assertSame('order_cancellation_not_allowed', $exception->problemCode);
        }
        self::assertSame('pending_payment', $order->refresh()->status);
        self::assertSame('active', DB::table('inventory_holds')->where('id', $order->inventory_hold_id)->value('status'));
    }

    /** @param array<string,mixed> $fixture */
    private function context(array $fixture): TenantContext
    {
        $membership = TenantMembership::query()->firstOrCreate(
            ['tenant_id' => $fixture['tenant']->id, 'user_id' => $fixture['actor']->id],
            ['status' => 'active', 'created_by_user_id' => $fixture['actor']->id],
        );

        return new TenantContext($fixture['tenant'], $membership, $fixture['actor']);
    }
}
