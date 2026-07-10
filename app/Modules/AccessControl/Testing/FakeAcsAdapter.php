<?php

namespace App\Modules\AccessControl\Testing;

use App\Modules\AccessControl\Contracts\AcsAdapter;
use App\Modules\AccessControl\Domain\Results\AcsHealthResult;

final class FakeAcsAdapter implements AcsAdapter
{
    /** @var list<array{operation:string}> */
    private array $calls = [];

    private ?AcsHealthResult $forcedHealth = null;

    private bool $unavailable = false;

    public function forceHealth(string $status, ?string $reasonCode = null): void
    {
        $this->forcedHealth = new AcsHealthResult($status, $reasonCode);
    }

    public function forceUnavailable(bool $unavailable): void
    {
        $this->unavailable = $unavailable;
    }

    public function isUnavailable(): bool
    {
        return $this->unavailable;
    }

    public function isAvailable(): bool
    {
        return ! $this->unavailable;
    }

    public function health(): AcsHealthResult
    {
        $this->calls[] = ['operation' => 'health'];

        return $this->forcedHealth ?? new AcsHealthResult('online');
    }

    /** @return list<array{operation:string}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
