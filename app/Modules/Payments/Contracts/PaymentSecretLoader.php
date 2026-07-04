<?php

namespace App\Modules\Payments\Contracts;

interface PaymentSecretLoader
{
    public function load(string $reference): string;
}
