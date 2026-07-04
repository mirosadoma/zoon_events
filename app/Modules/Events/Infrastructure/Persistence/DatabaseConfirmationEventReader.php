<?php

namespace App\Modules\Events\Infrastructure\Persistence;

use App\Modules\Events\Contracts\ConfirmationEventReader;
use App\Modules\Events\Domain\ConfirmationEventDetails;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;

final class DatabaseConfirmationEventReader implements ConfirmationEventReader
{
    public function find(string $tenantId, string $eventId): ?ConfirmationEventDetails
    {
        $event = Event::query()->where('tenant_id', $tenantId)->whereKey($eventId)->first(['name_en', 'name_ar']);

        return $event ? new ConfirmationEventDetails($event->name_en, $event->name_ar) : null;
    }
}
