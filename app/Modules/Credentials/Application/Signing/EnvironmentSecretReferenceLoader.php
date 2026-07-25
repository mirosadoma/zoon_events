<?php

namespace App\Modules\Credentials\Application\Signing;

use App\Modules\Credentials\Contracts\SecretReferenceLoader;
use App\Modules\Shared\Support\Environment\EnvironmentValue;
use InvalidArgumentException;

final class EnvironmentSecretReferenceLoader implements SecretReferenceLoader
{
    public function load(string $reference): string
    {
        $value = EnvironmentValue::get($reference);
        if ($value === null) {
            throw new InvalidArgumentException('Credential secret reference is unavailable.');
        }

        return $value;
    }
}
