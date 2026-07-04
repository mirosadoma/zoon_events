<?php

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\Domain\PaidOrderResult;
use App\Modules\Orders\Domain\PayableOrder;

interface OrderPaymentPort
{
    public function payable(string $publicReference, string $accessToken, string $host): PayableOrder;

    public function completeCaptured(
        string $orderId,
        string $paymentAccountId,
        int $capturedMinor,
        string $currency,
        bool $live,
    ): PaidOrderResult;
}
