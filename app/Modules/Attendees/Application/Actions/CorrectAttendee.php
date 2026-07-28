<?php

namespace App\Modules\Attendees\Application\Actions;

use App\Modules\Attendees\Domain\Events\AttendeeCorrected;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Attendees\Infrastructure\Persistence\Models\AttendeeCorrection;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Shared\Application\DataProtection\PersonalDataGuard;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class CorrectAttendee
{
    public function __construct(
        private PersonalDataGuard $guard,
        private AuditWriter $audit,
    ) {}

    /** @param array<string,string|null> $changes */
    public function execute(TenantContext $context, string $eventId, string $attendeeId, array $changes, string $reason): Attendee
    {
        $allowed = array_intersect_key($changes, array_flip(['first_name', 'last_name', 'email', 'phone']));

        return DB::transaction(function () use ($context, $eventId, $attendeeId, $allowed, $reason): Attendee {
            $attendee = Attendee::query()
                ->where('tenant_id', $context->tenant->id)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->findOrFail($attendeeId);
            $scope = "{$context->tenant->id}:{$eventId}:attendee";
            $updates = [];
            foreach ($allowed as $field => $value) {
                if ($value === null) {
                    $updates[$field.'_ciphertext'] = null;
                } else {
                    $encrypted = $this->guard->encryptString($value, $scope);
                    $updates[$field.'_ciphertext'] = $encrypted['ciphertext'];
                    $updates['encryption_key_id'] = $encrypted['key_id'];
                }
                if ($field === 'email') {
                    $updates['email_index'] = $value === null ? null : $this->guard->emailIndex($value);
                }
                if ($field === 'phone') {
                    $updates['phone_index'] = $value === null ? null : $this->guard->phoneIndex($value);
                }
            }
            $attendee->forceFill($updates)->save();
            AttendeeCorrection::query()->create([
                'tenant_id' => $context->tenant->id,
                'event_id' => $eventId,
                'attendee_id' => $attendee->id,
                'corrected_by_user_id' => $context->actor->id,
                'changed_fields' => array_fill_keys(array_keys($allowed), 'changed'),
                'reason' => $reason,
                'created_at' => now(),
            ]);
            $this->audit->writeTenant(
                'attendee.corrected',
                'succeeded',
                $context,
                targetType: 'attendee',
                targetId: $attendee->id,
                metadata: ['event_id' => $eventId, 'changed_fields' => array_keys($allowed), 'reason' => $reason],
            );
            event(new AttendeeCorrected(
                $context->tenant->id,
                $eventId,
                $attendee->id,
                array_keys($allowed),
            ));

            return $attendee->refresh();
        });
    }
}
