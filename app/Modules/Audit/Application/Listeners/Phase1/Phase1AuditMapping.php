<?php

namespace App\Modules\Audit\Application\Listeners\Phase1;

use App\Modules\Attendees\Domain\Events\AttendeeCorrected;
use App\Modules\Credentials\Domain\Events\CredentialLifecycleChanged;
use App\Modules\Events\Domain\Events\EventPublished;
use App\Modules\Notifications\Domain\Events\NotificationTerminalStateReached;
use App\Modules\Orders\Domain\Events\FreeRegistrationCompleted;
use App\Modules\Payments\Domain\Events\PaymentStateChanged;
use App\Modules\Payments\Domain\Events\RefundStateChanged;
use App\Modules\Registration\Domain\Events\RegistrationFormPublished;
use App\Modules\Ticketing\Domain\Events\InventoryStateChanged;
use App\Modules\Ticketing\Domain\Events\TicketTypeChanged;
use InvalidArgumentException;

final class Phase1AuditMapping
{
    /** @return array{action:string,target_type:string,target_id:string,metadata:array<string,string>} */
    public function for(object $event): array
    {
        return match (true) {
            $event instanceof EventPublished => [
                'action' => 'event.published',
                'target_type' => 'event',
                'target_id' => $event->eventId,
                'metadata' => [],
            ],
            $event instanceof RegistrationFormPublished => [
                'action' => 'registration_form.published',
                'target_type' => 'registration_form_version',
                'target_id' => $event->formVersionId,
                'metadata' => ['event_id' => $event->eventId],
            ],
            $event instanceof TicketTypeChanged => [
                'action' => 'ticket_type.'.$event->action,
                'target_type' => 'ticket_type',
                'target_id' => $event->ticketTypeId,
                'metadata' => ['event_id' => $event->eventId],
            ],
            $event instanceof InventoryStateChanged => [
                'action' => 'inventory.'.$event->transition,
                'target_type' => 'inventory_hold',
                'target_id' => $event->holdId,
                'metadata' => ['event_id' => $event->eventId, 'ticket_type_id' => $event->ticketTypeId],
            ],
            $event instanceof FreeRegistrationCompleted => [
                'action' => 'registration.free_completed',
                'target_type' => 'order',
                'target_id' => $event->orderId,
                'metadata' => [
                    'event_id' => $event->eventId,
                    'attendee_id' => $event->attendeeId,
                    'credential_id' => $event->credentialId,
                ],
            ],
            $event instanceof PaymentStateChanged => [
                'action' => 'payment.'.$event->status,
                'target_type' => 'payment_attempt',
                'target_id' => $event->attemptId,
                'metadata' => ['event_id' => $event->eventId, 'reason_code' => $event->reasonCode],
            ],
            $event instanceof AttendeeCorrected => [
                'action' => 'attendee.corrected',
                'target_type' => 'attendee',
                'target_id' => $event->attendeeId,
                'metadata' => ['event_id' => $event->eventId, 'changed_fields' => $event->changedFields],
            ],
            $event instanceof RefundStateChanged => [
                'action' => 'refund.'.$event->status,
                'target_type' => 'refund',
                'target_id' => $event->refundId,
                'metadata' => ['event_id' => $event->eventId],
            ],
            $event instanceof CredentialLifecycleChanged => [
                'action' => 'credential.'.$event->transition,
                'target_type' => 'credential',
                'target_id' => $event->credentialId,
                'metadata' => ['event_id' => $event->eventId, 'replacement_id' => $event->replacementId],
            ],
            $event instanceof NotificationTerminalStateReached => [
                'action' => 'notification.'.$event->status,
                'target_type' => 'notification',
                'target_id' => $event->notificationId,
                'metadata' => ['event_id' => $event->eventId, 'channel' => $event->channel],
            ],
            default => throw new InvalidArgumentException('Unsupported Phase 1 audit event.'),
        };
    }
}
