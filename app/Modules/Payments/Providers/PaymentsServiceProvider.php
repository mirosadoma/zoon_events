<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Application\PaymentGatewayRegistry;
use App\Modules\Payments\Application\Queries\PaymentOrderCancellationGuard;
use App\Modules\Payments\Contracts\OrderCancellationGuard;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Payments\Infrastructure\Adapters\Moyasar\MoyasarPaymentGateway;
use App\Modules\Payments\Infrastructure\Secrets\EnvironmentPaymentSecretLoader;
use App\Modules\Payments\Testing\FakePaymentGateway;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentSecretLoader::class, EnvironmentPaymentSecretLoader::class);
        $this->app->bind(OrderCancellationGuard::class, PaymentOrderCancellationGuard::class);
        $this->app->singleton(FakePaymentGateway::class);
        $this->app->singleton(MoyasarPaymentGateway::class);
        $this->app->singleton(PaymentGatewayRegistry::class, fn ($app) => new PaymentGatewayRegistry([
            'fake' => $app->make(FakePaymentGateway::class),
            'moyasar' => $app->make(MoyasarPaymentGateway::class),
        ]));
        $this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PaymentGatewayRegistry::class)->get((string) config('payments.default')));
    }
}
