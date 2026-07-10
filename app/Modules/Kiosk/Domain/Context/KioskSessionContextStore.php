<?php

namespace App\Modules\Kiosk\Domain\Context;

use App\Exceptions\FoundationException;
use App\Modules\Kiosk\Domain\ValueObjects\KioskSessionContext;

final class KioskSessionContextStore
{
    private ?KioskSessionContext $current = null;

    public function bind(KioskSessionContext $context): KioskSessionContext
    {
        if ($this->current !== null) {
            throw FoundationException::forbidden('kiosk_context_rebind', 'Kiosk session context is already bound for this request.');
        }

        $this->current = $context;

        return $this->current;
    }

    public function current(): KioskSessionContext
    {
        if ($this->current === null) {
            throw FoundationException::forbidden('kiosk_context_required', 'A kiosk session context is required.');
        }

        return $this->current;
    }

    public function currentOrNull(): ?KioskSessionContext
    {
        return $this->current;
    }

    public function clear(): void
    {
        $this->current = null;
    }
}
