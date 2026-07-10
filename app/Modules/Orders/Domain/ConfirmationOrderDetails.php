<?php

namespace App\Modules\Orders\Domain;

final readonly class ConfirmationOrderDetails
{
    public function __construct(public string $publicReference) {}
}
