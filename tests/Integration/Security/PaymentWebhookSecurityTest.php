<?php

namespace Tests\Integration\Security;

use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Testing\FakePaymentGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('payments')]
final class PaymentWebhookSecurityTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_forged_webhook_is_hidden_and_valid_duplicate_is_stored_once(): void
    {
        Queue::fake();
        $fixture = $this->createRegistrationFixture(500);
        $account = $this->account($fixture['tenant']->id);
        $account->forceFill(['adapter_key' => 'moyasar'])->save();
        config(['payments.moyasar.webhook_secret_reference' => 'WEBHOOK_TEST_SECRET']);
        app()->instance(PaymentSecretLoader::class, new class implements PaymentSecretLoader
        {
            public function load(string $reference): string
            {
                return 'synthetic-webhook-secret';
            }
        });
        $payload = ['id' => 'evt_synthetic', 'payment_id' => 'pay_synthetic'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $url = 'http://localhost/api/v1/webhooks/payments/moyasar/route-token';

        $this->withHeader('X-Moyasar-Signature', 'forged')->postJson($url, $payload)->assertNotFound();
        $denial = DB::table('audit_logs')->where('action', 'payment.callback_denied')->latest('occurred_at')->first();
        self::assertNotNull($denial);
        self::assertSame('signature_invalid', $denial->reason_code);
        self::assertStringNotContainsString('synthetic-webhook-secret', json_encode($denial));
        $signature = hash_hmac('sha256', $raw, 'synthetic-webhook-secret');
        $this->withHeader('X-Moyasar-Signature', $signature)->postJson($url, $payload)->assertAccepted();
        $this->withHeader('X-Moyasar-Signature', $signature)->postJson($url, $payload)->assertAccepted();

        self::assertSame(1, DB::table('payment_webhook_receipts')->where('payment_account_id', $account->id)->count());
    }

    public function test_amount_mismatch_never_completes_pending_order(): void
    {
        $fixture = $this->createRegistrationFixture(500);
        $this->account($fixture['tenant']->id);
        $started = $this->withHeader('Idempotency-Key', 'mismatch-start')->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated();
        app(FakePaymentGateway::class)->push(new PaymentResult(PaymentStatus::Captured, 'pay_wrong', 499, 0, 'SAR'));

        $this->withHeaders([
            'X-Order-Access-Token' => $started->json('data.access_token'),
            'Idempotency-Key' => 'mismatch-intent',
        ])->postJson(
            "http://register.example.test/api/v1/public/orders/{$started->json('data.public_reference')}/payment-intents",
            ['return_url' => 'https://register.example.test/return'],
        )->assertConflict()->assertJsonPath('code', 'payment_mismatch');

        self::assertSame('pending_payment', DB::table('orders')->where('public_reference', $started->json('data.public_reference'))->value('status'));
        self::assertSame(0, DB::table('attendees')->where('event_id', $fixture['event']->id)->count());
    }

    private function account(string $tenantId): PaymentAccount
    {
        return PaymentAccount::query()->create([
            'tenant_id' => $tenantId, 'adapter_key' => 'fake',
            'secret_reference' => 'unused', 'account_reference' => 'fake',
            'webhook_route_token_hash' => hash('sha256', 'route-token'), 'mode' => 'test',
            'currency' => 'SAR', 'status' => 'active',
        ]);
    }
}
