<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Jobs\DeliverNotificationJob;
use App\Modules\Notifications\Contracts\ConfirmationIntentCreator;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Shared\Application\DataProtection\BlindIndex;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmationIntentFactory implements ConfirmationIntentCreator
{
    public function __construct(private PersonalDataCipher $cipher, private BlindIndex $indexes) {}

    public function create(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $orderId,
        string $credentialId,
        string $email,
        string $locale,
        ?string $phone = null,
    ): string {
        $notification = $this->createChannel(
            $tenantId, $eventId, $attendeeId, $orderId, $credentialId,
            'email', $email, $locale, (string) config('notifications.email_adapter'),
        );
        $smsAdapter = (string) config('notifications.sms_adapter');
        if ($smsAdapter !== 'disabled' && is_string($phone) && preg_match('/^\+9665\d{8}$/', $phone) === 1) {
            $sms = $this->createChannel(
                $tenantId, $eventId, $attendeeId, $orderId, $credentialId,
                'sms', $phone, $locale, $smsAdapter,
            );
            $this->queueDelivery($sms->id);
        }

        $this->queueDelivery($notification->id);

        return $notification->id;
    }

    private function queueDelivery(string $notificationId): void
    {
        $deliver = function () use ($notificationId): void {
            if ((bool) config('notifications.dispatch_sync', false)) {
                DeliverNotificationJob::dispatchSync($notificationId);

                return;
            }

            DeliverNotificationJob::dispatch($notificationId);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($deliver);

            return;
        }

        $deliver();
    }

    private function createChannel(
        string $tenantId,
        string $eventId,
        string $attendeeId,
        string $orderId,
        string $credentialId,
        string $channel,
        string $destination,
        string $locale,
        string $adapter,
    ): Notification {
        $encrypted = $this->cipher->encrypt($destination, "{$tenantId}:{$eventId}:notification");
        $notification = Notification::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'channel' => $channel,
                'template_key' => 'registration_confirmation',
                'template_version' => 'v1',
            ],
            [
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'credential_id' => $credentialId,
                'locale' => $locale,
                'destination_ciphertext' => $encrypted['ciphertext'],
                'destination_index' => $channel === 'email'
                    ? $this->indexes->email($destination)
                    : $this->indexes->phone($destination),
                'encryption_key_id' => $encrypted['key_id'],
                'content_digest' => hash('sha256', "registration_confirmation:v1:{$locale}"),
                'adapter_key' => $adapter,
                'status' => 'pending',
            ],
        );

        return $notification;
    }
}
