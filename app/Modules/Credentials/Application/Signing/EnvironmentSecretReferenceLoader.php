<?php

namespace App\Modules\Credentials\Application\Signing;

use App\Modules\Credentials\Contracts\SecretReferenceLoader;
use Illuminate\Support\Env;
use InvalidArgumentException;

final class EnvironmentSecretReferenceLoader implements SecretReferenceLoader
{
    public function load(string $reference): string
    {
        $value = Env::get($reference);
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Credential secret reference is unavailable.');
        }

        return $value;
    }
}
