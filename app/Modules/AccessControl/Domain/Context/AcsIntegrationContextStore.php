<?php

namespace App\Modules\AccessControl\Domain\Context;

use App\Modules\AccessControl\Domain\ValueObjects\AcsIntegrationContext;
use App\Modules\Shared\Http\Problems\Phase4Problem;

final class AcsIntegrationContextStore
{
    private ?AcsIntegrationContext $current = null;

    public function bind(AcsIntegrationContext $context): AcsIntegrationContext
    {
        if ($this->current !== null) {
            throw Phase4Problem::make('acs_integration_invalid');
        }

        $this->current = $context;

        return $this->current;
    }

    public function current(): AcsIntegrationContext
    {
        if ($this->current === null) {
            throw Phase4Problem::make('acs_integration_invalid');
        }

        return $this->current;
    }

    public function currentOrNull(): ?AcsIntegrationContext
    {
        return $this->current;
    }

    public function clear(): void
    {
        $this->current = null;
    }
}
