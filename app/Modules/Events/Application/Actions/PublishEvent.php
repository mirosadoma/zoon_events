<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Application\Publication\PublicationReadiness;
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
    ) {}

    public function execute(TenantContext $context, Event $event): Event
    {
        return DB::transaction(function () use ($context, $event): Event {
            $event = Event::query()
                ->where('tenant_id', $context->tenant->id)
                ->lockForUpdate()
                ->findOrFail($event->id);
            $snapshot = [
                ...$event->only(['name_en', 'name_ar', 'timezone', 'start_at', 'end_at', 'registration_opens_at', 'registration_closes_at', 'active_form_version_id']),
                'active_ticket_types' => $this->tickets->countForEvent($context->tenant->id, $event->id),
                'branding_active' => $event->branding()->where('status', 'active')->exists(),
            ];
            if (! EventStatus::from($event->status)->canTransitionTo(EventStatus::Published) || ! $this->readiness->isReady($snapshot)) {
                throw Phase1Problem::make('event_not_publishable');
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
