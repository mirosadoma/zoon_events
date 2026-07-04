<?php

namespace App\Modules\Events\Contracts;

use App\Modules\Events\Domain\ConfirmationEventDetails;

interface ConfirmationEventReader
{
    public function find(string $tenantId, string $eventId): ?ConfirmationEventDetails;
}
