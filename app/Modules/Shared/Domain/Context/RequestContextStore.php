<?php

namespace App\Modules\Shared\Domain\Context;

class RequestContextStore
{
    private ?RequestContext $context = null;

    public function current(): ?RequestContext
    {
        return $this->context;
    }

    public function set(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }
}
