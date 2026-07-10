<?php

namespace Tests\Contract\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\PaymentContext;
use App\Modules\Payments\Domain\PaymentRequest;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Domain\PaymentStatus;
use PHPUnit\Framework\TestCase;

abstract class PaymentGatewayContractTestCase extends TestCase
{
    abstract protected function gateway(): PaymentGateway;

    abstract protected function queue(PaymentResult $result): void;

    public function test_gateway_maps_success_failure_and_unknown_without_provider_types(): void
    {
        foreach ([PaymentStatus::Captured, PaymentStatus::Failed, PaymentStatus::Unknown] as $status) {
            $this->queue(new PaymentResult($status, 'provider-id', $status === PaymentStatus::Captured ? 500 : 0, 0, 'SAR'));
            $result = $this->gateway()->create(
                new PaymentContext('tenant', 'account', 'correlation', 'key-'.$status->value, false, 1000),
                new PaymentRequest('order', 500, 'SAR', 'https://return.example.test'),
            );
            self::assertSame($status, $result->status);
            self::assertSame('SAR', $result->currency);
        }
    }

    public function test_refund_and_fetch_use_same_provider_neutral_result(): void
    {
        $this->queue(new PaymentResult(PaymentStatus::Captured, 'provider-id', 500, 0, 'SAR'));
        self::assertSame(PaymentStatus::Captured, $this->gateway()->fetch($this->context('fetch'), 'provider-id')->status);
        $this->queue(new PaymentResult(PaymentStatus::Refunded, 'provider-id', 500, 200, 'SAR'));
        self::assertSame(200, $this->gateway()->refund($this->context('refund'), 'provider-id', 200, 'SAR')->refundedMinor);
    }

    private function context(string $key): PaymentContext
    {
        return new PaymentContext('tenant', 'account', 'correlation', $key, false, 1000);
    }
}
