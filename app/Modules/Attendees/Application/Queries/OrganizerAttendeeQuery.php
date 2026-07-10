<?php

namespace App\Modules\Attendees\Application\Queries;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use Illuminate\Support\Collection;

final readonly class OrganizerAttendeeQuery
{
    public function __construct(private BlindIndex $indexes) {}

    /** @return Collection<int,Attendee> */
    public function execute(string $tenantId, string $eventId, ?string $email, int $limit = 50): Collection
    {
        return Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->when($email, fn ($query) => $query->where('email_index', $this->indexes->email($email)))
            ->orderByDesc('registered_at')
            ->limit(max(1, min(100, $limit)))
            ->get();
    }
}
