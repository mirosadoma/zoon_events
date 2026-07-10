<?php

namespace App\Modules\Events\Contracts;

interface PublicOrderHostAuthorizer
{
    public function allows(string $host, string $tenantId, string $eventId): bool;
}
