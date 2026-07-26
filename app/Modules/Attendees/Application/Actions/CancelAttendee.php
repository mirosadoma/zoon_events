<?php

namespace App\Modules\Attendees\Application\Actions;

use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class CancelAttendee
{
    public function __construct(
        private AuditWriter $audit,
    ) {}

    public function execute(TenantContext $context, string $eventId, string $attendeeId): Attendee
    {
        return DB::transaction(function () use ($context, $eventId, $attendeeId): Attendee {
            $attendee = Attendee::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->findOrFail($attendeeId);

            if ($attendee->registration_status === 'anonymized') {
                throw new InvalidArgumentException('Anonymized attendees cannot be cancelled.');
            }

            if ($attendee->registration_status === 'cancelled') {
                return $attendee;
            }

            $attendee->forceFill([
                'registration_status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            $this->audit->writeTenant(
                'attendee.cancelled',
                'succeeded',
                $context,
                targetType: 'attendee',
                targetId: $attendee->id,
                metadata: ['event_id' => $eventId],
            );

            return $attendee->refresh();
        });
    }
}
