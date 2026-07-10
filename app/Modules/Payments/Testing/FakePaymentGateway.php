<?php

namespace App\Modules\Payments\Testing;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;

final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<PaymentResult> */
    private array $results = [];

    /** @var list<array{operation:string,tenant_id:string,idempotency_key:string}> */
    public array $calls = [];

    public function push(PaymentResult $result): void
    {
        $this->results[] = $result;
    }

    public function create(PaymentContext $context, PaymentRequest $request): PaymentResult
    {
        return $this->next('create', $context, $request->currency);
    }

    public function fetch(PaymentContext $context, string $providerPaymentId): PaymentResult
    {
        return $this->next('fetch', $context, 'SAR');
    }

    public function refund(PaymentContext $context, string $providerPaymentId, int $amountMinor, string $currency): PaymentResult
    {
        return $this->next('refund', $context, $currency);
    }

    private function next(string $operation, PaymentContext $context, string $currency): PaymentResult
    {
        $this->calls[] = ['operation' => $operation, 'tenant_id' => $context->tenantId, 'idempotency_key' => $context->idempotencyKey];

        return array_shift($this->results) ?? new PaymentResult(PaymentStatus::Unknown, null, 0, 0, $currency, 'unknown_outcome');
    }
}
