<?php

namespace Tests\Integration\Payments;

use App\Modules\Orders\Infrastructure\Persistence\Models\Order;
use App\Modules\Payments\Application\Actions\ReconcilePaymentAttempt;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Payments\Testing\FakePaymentGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('payment-reconciliation')]
final class PaymentReconciliationTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_unknown_attempt_is_recovered_only_by_authoritative_fetch(): void
    {
        $fixture = $this->createRegistrationFixture(500);
        $account = PaymentAccount::query()->create([
            'tenant_id' => $fixture['tenant']->id, 'adapter_key' => 'fake',
            'secret_reference' => 'unused', 'account_reference' => 'fake',
            'webhook_route_token_hash' => hash('sha256', 'route'), 'mode' => 'test',
            'currency' => 'SAR', 'status' => 'active',
        ]);
        $start = $this->withHeader('Idempotency-Key', 'reconcile-start')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        app(FakePaymentGateway::class)->push(new PaymentResult(PaymentStatus::Unknown, 'pay_reconcile', 0, 0, 'SAR', 'unknown_outcome'));
        $this->withHeaders([
            'X-Order-Access-Token' => $start->json('data.access_token'),
            'Idempotency-Key' => 'reconcile-intent',
        ])->postJson(
            "http://register.example.test/api/v1/public/orders/{$start->json('data.public_reference')}/payment-intents",
            ['return_url' => 'https://register.example.test/return'],
        )->assertCreated()->assertJsonPath('data.status', 'unknown');
        $attempt = PaymentAttempt::query()->where('payment_account_id', $account->id)->firstOrFail();

        app(FakePaymentGateway::class)->push(new PaymentResult(PaymentStatus::Captured, 'pay_reconcile', 500, 0, 'SAR'));
        app(ReconcilePaymentAttempt::class)->execute($attempt);

        self::assertSame('captured', $attempt->refresh()->status);
        self::assertSame('paid', Order::query()->findOrFail($attempt->order_id)->status);
    }
}
