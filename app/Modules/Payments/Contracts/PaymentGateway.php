<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;

interface PaymentGateway
{
    public function create(PaymentContext $context, PaymentRequest $request): PaymentResult;

    public function fetch(PaymentContext $context, string $providerPaymentId): PaymentResult;

    public function refund(PaymentContext $context, string $providerPaymentId, int $amountMinor, string $currency): PaymentResult;
}
