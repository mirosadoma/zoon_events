<?php

namespace App\Modules\Events\Contracts;

interface EventScope
{
    public function exists(string $tenantId, string $eventId): bool;

    public function setActiveFormVersion(string $tenantId, string $eventId, string $formVersionId): void;
}
