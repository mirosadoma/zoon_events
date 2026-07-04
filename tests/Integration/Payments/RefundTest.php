<?php

namespace Tests\Integration\Payments;

use App\Exceptions\FoundationException;
use App\Modules\Payments\Application\Actions\RequestRefund;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Testing\FakePaymentGateway;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('refunds')]
final class RefundTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_partial_duplicate_and_excess_refunds_are_bounded(): void
    {
        [$fixture, $orderId] = $this->capturedOrder();
        $context = $this->context($fixture);
        app(FakePaymentGateway::class)->push(new PaymentResult(
            PaymentStatus::PartiallyRefunded,
            'refund_synthetic',
            500,
            200,
            'SAR',
        ));
        $first = app(RequestRefund::class)->execute($context, $fixture['event']->id, $orderId, 200, 'Customer request', 'refund-key');
        $duplicate = app(RequestRefund::class)->execute($context, $fixture['event']->id, $orderId, 200, 'Customer request', 'refund-key');

        self::assertSame($first->id, $duplicate->id);
        self::assertSame('partially_refunded', DB::table('orders')->where('id', $orderId)->value('status'));
        self::assertSame(1, DB::table('refunds')->where('order_id', $orderId)->count());

        $this->expectException(FoundationException::class);
        app(RequestRefund::class)->execute($context, $fixture['event']->id, $orderId, 301, 'Excess', 'refund-excess');
    }

    public function test_unknown_refund_remains_reconcilable_without_changing_order(): void
    {
        [$fixture, $orderId] = $this->capturedOrder();
        app(FakePaymentGateway::class)->push(new PaymentResult(PaymentStatus::Unknown, null, 500, 0, 'SAR', 'unknown_outcome'));
        $refund = app(RequestRefund::class)->execute(
            $this->context($fixture),
            $fixture['event']->id,
            $orderId,
            100,
            'Unknown test',
            'refund-unknown',
        );

        self::assertSame('unknown', $refund->status);
        self::assertSame('paid', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    /** @return array{array<string,mixed>,string} */
    private function capturedOrder(): array
    {
        $fixture = $this->createRegistrationFixture(500);
        PaymentAccount::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'adapter_key' => 'fake',
            'secret_reference' => 'unused', 'account_reference' => 'fake',
            'webhook_route_token_hash' => hash('sha256', 'route'), 'mode' => 'test',
            'currency' => 'SAR', 'status' => 'active',
        ]);
        $started = $this->withHeader('Idempotency-Key', 'refund-start-'.$fixture['event']->id)->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        app(FakePaymentGateway::class)->push(new PaymentResult(PaymentStatus::Captured, 'pay_'.$fixture['event']->id, 500, 0, 'SAR'));
        $this->withHeaders([
            'X-Order-Access-Token' => $started->json('data.access_token'),
            'Idempotency-Key' => 'capture-'.$fixture['event']->id,
        ])->postJson(
            "http://register.example.test/api/v1/public/orders/{$started->json('data.public_reference')}/payment-intents",
            ['return_url' => 'https://register.example.test/return'],
        )->assertCreated();

        return [$fixture, DB::table('orders')->where('public_reference', $started->json('data.public_reference'))->value('id')];
    }

    private function context(array $fixture): TenantContext
    {
        $membership = TenantMembership::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'user_id' => $fixture['actor']->id,
            'status' => 'active', 'created_by_user_id' => $fixture['actor']->id,
        ]);

        return new TenantContext($fixture['tenant'], $membership, $fixture['actor']);
    }
}
