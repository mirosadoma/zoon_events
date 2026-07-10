<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Orders\Application\Actions\ApplyOrderRefund;
use App\Modules\Orders\Application\Actions\CompletePaidRegistration;
use App\Modules\Orders\Contracts\ConfirmationOrderReader;
use App\Modules\Orders\Contracts\OrderPaymentPort;
use App\Modules\Orders\Contracts\OrderPersonalDataAnonymizer;
use App\Modules\Orders\Contracts\OrderRefundPort;
use App\Modules\Orders\Infrastructure\Persistence\DatabaseConfirmationOrderReader;
use App\Modules\Orders\Infrastructure\Persistence\DatabaseOrderPersonalDataAnonymizer;
use Illuminate\Support\ServiceProvider;

final class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderPaymentPort::class, CompletePaidRegistration::class);
        $this->app->bind(OrderRefundPort::class, ApplyOrderRefund::class);
        $this->app->bind(ConfirmationOrderReader::class, DatabaseConfirmationOrderReader::class);
        $this->app->bind(OrderPersonalDataAnonymizer::class, DatabaseOrderPersonalDataAnonymizer::class);
    }
}
