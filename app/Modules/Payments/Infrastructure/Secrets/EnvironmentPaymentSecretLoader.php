<?php

namespace App\Modules\Payments\Infrastructure\Secrets;

use App\Modules\Payments\Contracts\PaymentSecretLoader;
use Illuminate\Support\Env;
use InvalidArgumentException;

final class EnvironmentPaymentSecretLoader implements PaymentSecretLoader
{
    public function load(string $reference): string
    {
        $value = Env::get($reference);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Payment secret reference is unavailable.');
        }

        return $value;
    }
}
