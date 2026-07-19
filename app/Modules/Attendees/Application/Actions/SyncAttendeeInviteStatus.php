<?php

namespace App\Modules\Attendees\Application\Actions;

use App\Modules\Attendees\Domain\AttendeeInviteStatus;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use Illuminate\Support\Facades\DB;

final readonly class SyncAttendeeInviteStatus
{
    public function __construct(private BlindIndex $indexes) {}

    public function markRegistered(string $tenantId, string $eventId, string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        EventRegistrationInvite::query()
            ->where('event_id', $eventId)
            ->where('email', $email)
            ->whereIn('invite_status', [
                AttendeeInviteStatus::NotRegistered->value,
                AttendeeInviteStatus::Registered->value,
            ])
            ->update(['invite_status' => AttendeeInviteStatus::Registered->value]);
    }

    public function markAttended(string $tenantId, string $eventId, string $attendeeId): void
    {
        $attendee = Attendee::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->find($attendeeId);

        if ($attendee === null) {
            return;
        }

        $attendee->forceFill([
            'invite_status' => AttendeeInviteStatus::Attended->value,
        ])->save();

        // Best-effort: mark invite by matching email index when possible is not decryptable here.
        // Invite email updates happen from registration finalize; scan uses attendee id primarily.
    }

    public function markAttendedForEmail(string $eventId, string $email): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        EventRegistrationInvite::query()
            ->where('event_id', $eventId)
            ->where('email', $email)
            ->whereIn('invite_status', [
                AttendeeInviteStatus::NotRegistered->value,
                AttendeeInviteStatus::Registered->value,
            ])
            ->update(['invite_status' => AttendeeInviteStatus::Attended->value]);

        Attendee::query()
            ->where('event_id', $eventId)
            ->where('email_index', $this->indexes->email($email))
            ->whereIn('invite_status', [
                AttendeeInviteStatus::Registered->value,
                AttendeeInviteStatus::NotAttended->value,
            ])
            ->update(['invite_status' => AttendeeInviteStatus::Attended->value]);
    }

    public function markNotAttendedForEndedEvents(): int
    {
        $updated = 0;

        Event::query()
            ->whereNotNull('end_at')
            ->where('end_at', '<', now())
            ->whereIn('status', ['published', 'registration_open', 'registration_closed', 'live', 'completed'])
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$updated): void {
                foreach ($events as $event) {
                    $updated += DB::transaction(function () use ($event): int {
                        $attendees = Attendee::query()
                            ->where('tenant_id', $event->tenant_id)
                            ->where('event_id', $event->id)
                            ->where('invite_status', AttendeeInviteStatus::Registered->value)
                            ->where('checkin_status', '!=', 'checked_in')
                            ->update(['invite_status' => AttendeeInviteStatus::NotAttended->value]);

                        $invites = EventRegistrationInvite::query()
                            ->where('event_id', $event->id)
                            ->whereIn('invite_status', [
                                AttendeeInviteStatus::NotRegistered->value,
                                AttendeeInviteStatus::Registered->value,
                            ])
                            ->update(['invite_status' => AttendeeInviteStatus::NotAttended->value]);

                        return $attendees + $invites;
                    });
                }
            });

        return $updated;
    }
}
