<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Events\Domain\EventStatus;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Shared\Http\Problems\Phase1Problem;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UnpublishEvent
{
    public function __construct(private AuditWriter $audit) {}

    public function execute(TenantContext $context, Event $event): Event
    {
        return DB::transaction(function () use ($context, $event): Event {
            $event = Event::query()
                ->where('tenant_id', $context->tenant->id)
                ->lockForUpdate()
                ->findOrFail($event->id);

            $blockers = self::blockersFor($event);
            if ($blockers !== []) {
                throw Phase1Problem::make('event_not_unpublishable', ['reasons' => $blockers]);
            }

            $status = EventStatus::from($event->status);
            if (! $status->canTransitionTo(EventStatus::Configured)) {
                throw Phase1Problem::make('event_not_unpublishable', ['reasons' => ['status_'.$event->status]]);
            }

            $event->forceFill([
                'status' => EventStatus::Configured->value,
                'published_by_user_id' => null,
                'published_at' => null,
            ])->save();

            $this->audit->writeTenant(
                'event.unpublished',
                'succeeded',
                $context,
                targetType: 'event',
                targetId: $event->id,
            );

            return $event->refresh();
        });
    }

    /** @return list<string> */
    public static function blockersFor(Event $event): array
    {
        $blockers = [];
        $status = EventStatus::tryFrom((string) $event->status);

        if ($status === null || ! in_array($status, [
            EventStatus::Published,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
        ], true)) {
            $blockers[] = 'status_'.$event->status;
        }

        if ($event->start_at !== null && CarbonImmutable::parse($event->start_at)->lessThanOrEqualTo(CarbonImmutable::now())) {
            $blockers[] = 'event_started';
        }

        $hasRegistrations = Attendee::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('event_id', $event->id)
            ->where('registration_status', 'registered')
            ->exists();

        if ($hasRegistrations) {
            $blockers[] = 'has_registrations';
        }

        return $blockers;
    }

    public static function canUnpublish(Event $event): bool
    {
        $status = EventStatus::tryFrom((string) $event->status);
        if ($status === null || ! $status->canTransitionTo(EventStatus::Configured)) {
            return false;
        }

        return self::blockersFor($event) === [];
    }
}
