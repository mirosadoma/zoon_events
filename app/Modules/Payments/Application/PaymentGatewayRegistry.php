<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;

final readonly class PaymentGatewayRegistry
{
    /** @param array<string,PaymentGateway> $gateways */
    public function __construct(private array $gateways) {}

    public function get(string $key): PaymentGateway
    {
        return $this->gateways[$key] ?? throw new InvalidArgumentException('Payment gateway is not configured.');
    }
}
