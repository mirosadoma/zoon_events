<?php

namespace App\Modules\Payments\Infrastructure\Secrets;

use App\Modules\Payments\Contracts\PaymentSecretLoader;
use App\Modules\Shared\Support\Environment\EnvironmentValue;
use InvalidArgumentException;

final class EnvironmentPaymentSecretLoader implements PaymentSecretLoader
{
    public function load(string $reference): string
    {
        $value = EnvironmentValue::get($reference);
        if ($value === null) {
            throw new InvalidArgumentException('Payment secret reference is unavailable.');
        }

        return $value;
    }
}
