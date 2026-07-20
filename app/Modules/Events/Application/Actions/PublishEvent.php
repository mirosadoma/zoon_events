<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Application\Publication\PublicationReadiness;
use App\Modules\Events\Application\Registration\EnsureDefaultRegistrationSlot;
use App\Modules\Events\Domain\Events\EventPublished;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Ticketing\Contracts\ActiveTicketCounter;
use Illuminate\Support\Facades\DB;

final readonly class PublishEvent
{
    public function __construct(
        private PublicationReadiness $readiness,
        private AuditWriter $audit,
        private ActiveTicketCounter $tickets,
        private EnsureDefaultRegistrationSlot $registrationSlots,
    ) {}

    public function execute(TenantContext $context, Event $event): Event
    {
        return DB::transaction(function () use ($context, $event): Event {
            $event = Event::query()
                ->where('tenant_id', $context->tenant->id)
                ->lockForUpdate()
                ->findOrFail($event->id);
            $this->registrationSlots->execute($event, $context->actor->id);
            $event->refresh();

            $missing = $this->readiness->missingForEvent(
                $event,
                $this->tickets->countOrganizerTicketTypesForEvent($context->tenant->id, $event->id),
            );

            // Publish is allowed from draft/configured; ignore status blockers that belong to post-publish states.
            $missing = array_values(array_filter(
                $missing,
                static fn (string $item): bool => ! str_starts_with($item, 'status_'),
            ));

            if ($missing !== []) {
                throw Phase1Problem::eventNotPublishable($missing);
            }

            $status = EventStatus::from($event->status);

            if ($status === EventStatus::Draft) {
                $event->forceFill(['status' => EventStatus::Configured->value])->save();
                $status = EventStatus::Configured;
            }

            if (! $status->canTransitionTo(EventStatus::Published)) {
                throw Phase1Problem::eventNotPublishable(['status_'.$event->status]);
            }
            $event->forceFill([
                'status' => 'published',
                'published_by_user_id' => $context->actor->id,
                'published_at' => now(),
            ])->save();
            $this->audit->writeTenant('event.published', 'succeeded', $context, targetType: 'event', targetId: $event->id);
            event(new EventPublished($context->tenant->id, $event->id, $context->actor->id));

            return $event->refresh();
        });
    }
}
