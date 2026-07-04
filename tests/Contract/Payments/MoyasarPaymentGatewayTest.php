<?php

namespace Tests\Contract\Payments;

use App\Models\User;
use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Adapters\Moyasar\MoyasarPaymentGateway;
use App\Modules\Payments\Infrastructure\Persistence\Models\PaymentAccount;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Phase1MySqlTestCase;

#[Group('phase-1')]
#[Group('payments')]
final class MoyasarPaymentGatewayTest extends Phase1MySqlTestCase
{
    use DatabaseTransactions;

    public function test_create_uses_bounded_http_and_maps_response_without_leaking_secret(): void
    {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->create(['created_by_user_id' => $actor->id]);
        $account = PaymentAccount::query()->create([
            'tenant_id' => $tenant->id, 'adapter_key' => 'moyasar', 'secret_reference' => 'SYNTHETIC_SECRET',
            'account_reference' => 'merchant-test', 'webhook_route_token_hash' => hash('sha256', 'route'),
            'mode' => 'test', 'currency' => 'SAR', 'status' => 'active',
        ]);
        Http::fake(['*' => Http::response(['id' => 'pay_test', 'status' => 'paid', 'amount' => 500, 'currency' => 'SAR'], 200)]);
        $loader = new class implements PaymentSecretLoader
        {
            public function load(string $reference): string
            {
                return 'synthetic-secret-value';
            }
        };
        $gateway = new MoyasarPaymentGateway(app(Factory::class), $loader);
        $result = $gateway->create(
            new PaymentContext($tenant->id, $account->id, 'correlation', 'idempotency', false, 1000),
            new PaymentRequest('ord_test', 500, 'SAR', 'https://return.example.test'),
        );

        self::assertSame(PaymentStatus::Captured, $result->status);
        Http::assertSent(function ($request): bool {
            self::assertStringNotContainsString('synthetic-secret-value', $request->body());

            return $request->url() === 'https://api.moyasar.com/v1/payments'
                && $request['amount'] === 500;
        });
    }
}
