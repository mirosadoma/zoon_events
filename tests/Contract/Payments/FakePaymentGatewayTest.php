<?php

namespace Tests\Contract\Payments;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\PaymentResult;
use App\Modules\Payments\Testing\FakePaymentGateway;
use PHPUnit\Framework\Attributes\Group;

#[Group('phase-1')]
#[Group('payments')]
final class FakePaymentGatewayTest extends PaymentGatewayContractTestCase
{
    private FakePaymentGateway $fake;

    protected function setUp(): void
    {
        $this->fake = new FakePaymentGateway;
    }

    protected function gateway(): PaymentGateway
    {
        return $this->fake;
    }

    protected function queue(PaymentResult $result): void
    {
        $this->fake->push($result);
    }
}
