<?php

namespace App\Modules\Events\Domain\Context;

use LogicException;

final class PublicEventContextStore
{
    private ?PublicEventContext $context = null;

    public function bind(PublicEventContext $context): void
    {
        if ($this->context !== null) {
            throw new LogicException('Public event context is already bound.');
        }

        $this->context = $context;
    }

    public function current(): PublicEventContext
    {
        return $this->context ?? throw new LogicException('Public event context is not bound.');
    }

    public function currentOrNull(): ?PublicEventContext
    {
        return $this->context;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
