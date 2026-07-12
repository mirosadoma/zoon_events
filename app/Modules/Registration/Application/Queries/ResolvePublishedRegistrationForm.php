<?php

namespace App\Modules\Registration\Application\Queries;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Registration\Infrastructure\Persistence\Models\RegistrationFormVersion;

final class ResolvePublishedRegistrationForm
{
    public function forEvent(Event $event): RegistrationFormVersion
    {
        $query = RegistrationFormVersion::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('status', 'published');

        if ($event->active_form_version_id !== null) {
            $active = (clone $query)->whereKey($event->active_form_version_id)->first();
            if ($active !== null) {
                return $active;
            }
        }

        return $query->latest('version')->firstOrFail();
    }
}
