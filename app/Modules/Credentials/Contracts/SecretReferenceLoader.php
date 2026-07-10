<?php

namespace App\Modules\Credentials\Contracts;

interface SecretReferenceLoader
{
    public function load(string $reference): string;
}
