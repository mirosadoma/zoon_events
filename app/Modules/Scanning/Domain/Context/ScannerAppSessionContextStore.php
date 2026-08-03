<?php

namespace App\Modules\Scanning\Domain\Context;

use App\Modules\Scanning\Domain\ValueObjects\ScannerAppSessionContext;
use RuntimeException;

final class ScannerAppSessionContextStore
{
    private ?ScannerAppSessionContext $current = null;

    public function bind(ScannerAppSessionContext $context): void
    {
        $this->current = $context;
    }

    public function clear(): void
    {
        $this->current = null;
    }

    public function current(): ScannerAppSessionContext
    {
        if ($this->current === null) {
            throw new RuntimeException('Scanner app session context is not bound.');
        }

        return $this->current;
    }

    public function currentOrNull(): ?ScannerAppSessionContext
    {
        return $this->current;
    }
}
