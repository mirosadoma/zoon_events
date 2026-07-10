<?php

namespace Tests\Integration\Payments;

use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Payments\Testing\FakePaymentGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('paid-registration')]
#[Group('payments')]
final class PaidRegistrationTest extends Phase1MySqlTestCase
{
    use CreatesPhase1RegistrationFixture;
    use DatabaseTransactions;

    public function test_authoritative_capture_completes_once_across_browser_replay(): void
    {
        $fixture = $this->createRegistrationFixture(500);
        $this->account($fixture['tenant']->id);
        $order = $this->start($fixture, 'paid-start');
        app(FakePaymentGateway::class)->push(new PaymentResult(
            PaymentStatus::Captured,
            'pay_synthetic',
            500,
            0,
            'SAR',
        ));
        $url = "http://register.example.test/api/v1/public/orders/{$order['reference']}/payment-intents";
        $headers = [
            'X-Order-Access-Token' => $order['token'],
            'Idempotency-Key' => 'payment-intent-1',
        ];

        $this->withHeaders($headers)->postJson($url, ['return_url' => 'https://register.example.test/return'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'captured');
        $this->withHeaders($headers)->postJson($url, ['return_url' => 'https://register.example.test/return'])
            ->assertCreated();

        self::assertSame('paid', DB::table('orders')->where('public_reference', $order['reference'])->value('status'));
        self::assertSame(1, DB::table('payment_attempts')->where('order_id', $order['id'])->count());
        self::assertSame(1, DB::table('attendees')->where('order_id', $order['id'])->count());
        self::assertSame(1, DB::table('credentials')->where('event_id', $fixture['event']->id)->count());
        self::assertSame(1, DB::table('notifications')->where('order_id', $order['id'])->count());
    }

    public function test_failed_or_unknown_provider_outcome_preserves_pending_order(): void
    {
        foreach ([PaymentStatus::Failed, PaymentStatus::Unknown] as $index => $status) {
            $fixture = $this->createRegistrationFixture(500);
            $this->account($fixture['tenant']->id);
            $order = $this->start($fixture, 'pending-'.$index);
            app(FakePaymentGateway::class)->push(new PaymentResult($status, 'pay_'.$index, 0, 0, 'SAR', $status === PaymentStatus::Unknown ? 'unknown_outcome' : 'declined'));

            $this->withHeaders([
                'X-Order-Access-Token' => $order['token'],
                'Idempotency-Key' => 'intent-'.$index,
            ])->postJson(
                "http://register.example.test/api/v1/public/orders/{$order['reference']}/payment-intents",
                ['return_url' => 'https://register.example.test/return'],
            )->assertCreated();

            self::assertSame('pending_payment', DB::table('orders')->where('id', $order['id'])->value('status'));
            self::assertSame(0, DB::table('attendees')->where('order_id', $order['id'])->count());
        }
    }

    private function account(string $tenantId): PaymentAccount
    {
        return PaymentAccount::query()->create([
            'tenant_id' => $tenantId,
            'adapter_key' => 'fake',
            'secret_reference' => 'unused-test-reference',
            'account_reference' => 'fake-account',
            'webhook_route_token_hash' => hash('sha256', 'route-token'),
            'mode' => 'test',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
    }

    /** @return array{id:string,reference:string,token:string} */
    private function start(array $fixture, string $key): array
    {
        $response = $this->withHeader('Idempotency-Key', $key)->postJson(
            "http://register.example.test/api/v1/public/events/{$fixture['event']->slug}/registrations",
            $this->registrationPayload($fixture),
        )->assertCreated()->assertJsonPath('data.status', 'pending_payment');

        return [
            'id' => DB::table('orders')->where('public_reference', $response->json('data.public_reference'))->value('id'),
            'reference' => $response->json('data.public_reference'),
            'token' => $response->json('data.access_token'),
        ];
    }
}
