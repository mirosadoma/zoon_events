<?php

namespace App\Modules\Ai\Infrastructure\Secrets;

use App\Modules\Ai\Contracts\AiSecretLoader;
use App\Modules\Shared\Support\Environment\EnvironmentValue;
use InvalidArgumentException;

final class EnvironmentAiSecretLoader implements AiSecretLoader
{
    public function load(string $reference): string
    {
        $value = EnvironmentValue::get($reference);
        if ($value === null) {
            throw new InvalidArgumentException('AI secret reference is unavailable.');
        }

        return $value;
    }
}
