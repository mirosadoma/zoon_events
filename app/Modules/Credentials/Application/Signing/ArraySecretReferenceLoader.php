<?php

namespace App\Modules\Credentials\Application\Signing;

use App\Modules\Credentials\Contracts\SecretReferenceLoader;
use InvalidArgumentException;

final readonly class ArraySecretReferenceLoader implements SecretReferenceLoader
{
    /** @param array<string,string> $secrets */
    public function __construct(private array $secrets) {}

    public function load(string $reference): string
    {
        return $this->secrets[$reference] ?? throw new InvalidArgumentException('Credential secret reference is unavailable.');
    }
}
