<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Configuration\ConfigurationValidator;
use App\Modules\Operations\Application\Health\HealthCheckResult;
use App\Modules\Operations\Contracts\HealthCheck;

abstract class AbstractConfigurationHealthCheck implements HealthCheck
{
    /** @param list<string> $keyPrefixes */
    public function __construct(
        private readonly ConfigurationValidator $validator,
        private readonly array $keyPrefixes,
    ) {}

    public function run(): HealthCheckResult
    {
        $started = microtime(true);
        $invalid = collect($this->validator->validate())->contains(
            fn ($issue): bool => collect($this->keyPrefixes)->contains(
                fn (string $prefix): bool => str_starts_with($issue->key, $prefix),
            ),
        );

        return new HealthCheckResult(
            $this->category(),
            $invalid ? 'unavailable' : 'ok',
            (int) round((microtime(true) - $started) * 1000),
            $invalid ? $this->category().'_configuration_invalid' : null,
        );
    }
}
