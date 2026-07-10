<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class ArchiveEvent
{
    public function __construct(private AuditWriter $audit) {}

    public function execute(TenantContext $context, Event $event, string $reason): Event
    {
        return DB::transaction(function () use ($context, $event, $reason): Event {
            if (! EventStatus::from($event->status)->canTransitionTo(EventStatus::Archived)) {
                throw Phase1Problem::make('event_not_publishable');
            }
            $event->forceFill(['status' => 'archived', 'archived_at' => now()])->save();
            $this->audit->writeTenant(
                'event.archived', 'succeeded', $context,
                targetType: 'event', targetId: $event->id, metadata: ['reason' => $reason],
            );

            return $event->refresh();
        });
    }
}
