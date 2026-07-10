<?php

namespace App\Modules\Operations\Application\Configuration;

final readonly class ConfigurationIssue
{
    public function __construct(
        public string $key,
        public string $code,
        public string $message,
        public string $severity = 'error',
    ) {}

    public function toArray(): array
    {
        return ['key' => $this->key, 'code' => $this->code, 'message' => $this->message, 'severity' => $this->severity];
    }
}
