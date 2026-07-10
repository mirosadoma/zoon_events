<?php

namespace App\Modules\Events\Infrastructure\Persistence;

use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class DatabaseEventScope implements EventScope
{
    public function exists(string $tenantId, string $eventId): bool
    {
        return Event::query()->where('tenant_id', $tenantId)->whereKey($eventId)->exists();
    }

    public function setActiveFormVersion(string $tenantId, string $eventId, string $formVersionId): void
    {
        $updated = Event::query()->where('tenant_id', $tenantId)->whereKey($eventId)->update(['active_form_version_id' => $formVersionId]);
        if ($updated !== 1) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$eventId]);
        }
    }
}
