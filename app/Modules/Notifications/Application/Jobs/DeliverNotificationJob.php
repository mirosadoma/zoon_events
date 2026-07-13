<?php

namespace App\Modules\Notifications\Application\Jobs;

use App\Modules\AdminConsole\Application\SiteSettingsRepository;
use App\Modules\Attendees\Infrastructure\Persistence\Models\Attendee;
use App\Modules\Credentials\Infrastructure\Persistence\Models\Credential;
use App\Modules\Events\Contracts\ConfirmationEventReader;
use App\Modules\Notifications\Application\NotificationAdapterRegistry;
use App\Modules\Notifications\Application\Rendering\ConfirmationRenderer;
use App\Modules\Notifications\Domain\Events\NotificationTerminalStateReached;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationResult;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use App\Modules\Orders\Contracts\ConfirmationOrderReader;
use App\Modules\Shared\Application\DataProtection\PersonalDataCipher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

final class DeliverNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800];

    public function __construct(public readonly string $notificationId)
    {
        $this->afterCommit();
    }

    public function handle(
        NotificationAdapterRegistry $adapters,
        ConfirmationRenderer $renderer,
        PersonalDataCipher $cipher,
        ConfirmationEventReader $events,
        ConfirmationOrderReader $orders,
        SiteSettingsRepository $siteSettings,
    ): void {
        $notification = DB::transaction(function (): ?Notification {
            $row = Notification::query()->lockForUpdate()->find($this->notificationId);
            if (! $row || in_array($row->status, ['sent', 'delivered', 'permanent_failure'], true)) {
                return null;
            }
            if ($row->status === 'processing' && $row->updated_at?->isAfter(now()->subMinutes(10))) {
                return null;
            }
            $row->forceFill(['status' => 'processing', 'attempt_count' => $row->attempt_count + 1])->save();

            return $row;
        });

        if (! $notification) {
            return;
        }

        try {
            $event = $events->find($notification->tenant_id, $notification->event_id);
            $order = $orders->find($notification->tenant_id, $notification->event_id, $notification->order_id);
            if (! $event || ! $order) {
                $result = new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'scope_not_found');
            } else {
                $qrPayload = '';
                if ($notification->credential_id !== null && $notification->credential_id !== '') {
                    $credential = Credential::query()
                        ->where('tenant_id', $notification->tenant_id)
                        ->whereKey($notification->credential_id)
                        ->first();

                    if ($credential instanceof Credential) {
                        $qrPayload = $order->publicReference;
                    }
                }

                $locale = in_array($notification->locale, ['ar', 'en'], true) ? $notification->locale : 'en';
                $settings = $siteSettings->toPublicArray();
                $attendeeName = $this->resolveAttendeeName($cipher, $notification);
                $credentialUrl = URL::temporarySignedRoute(
                    'public.order.show',
                    now()->addDays(90),
                    ['locale' => $locale, 'public_reference' => $order->publicReference],
                );

                $rendered = $renderer->render($locale, [
                    'event_name' => $event->name($locale),
                    'order_reference' => $order->publicReference,
                    'credential_url' => $credentialUrl,
                    'qr_payload' => $qrPayload,
                    'attendee_name' => $attendeeName,
                    'app_name' => $locale === 'ar'
                        ? (string) ($settings['app_name_ar'] ?? config('zonetec.name', 'Zonetec'))
                        : (string) ($settings['app_name_en'] ?? config('zonetec.name', 'Zonetec')),
                    'support_email' => (string) ($settings['support_email'] ?? config('mail.from.address')),
                ]);
                $destination = $cipher->decrypt([
                    'key_id' => $notification->encryption_key_id,
                    'ciphertext' => $notification->destination_ciphertext,
                ], "{$notification->tenant_id}:{$notification->event_id}:notification");
                $channel = NotificationChannel::from($notification->channel);
                $result = $adapters->get($notification->adapter_key)->send(new NotificationRequest(
                    $notification->tenant_id,
                    $notification->id,
                    $channel,
                    $destination,
                    $channel === NotificationChannel::Email
                        ? (string) config('mail.from.address')
                        : (string) config('notifications.unifonic.sender_id'),
                    $rendered['subject'],
                    $rendered['body'],
                    $notification->locale,
                    $notification->id,
                    $notification->id,
                    $rendered['embedded_images'],
                ));
            }
        } catch (Throwable) {
            $result = new NotificationResult(NotificationStatus::TemporaryFailure, reasonCode: 'delivery_exception');
        }
        try {
            $this->finish($notification, $result->status, $result->providerMessageId, $result->reasonCode);
        } catch (Throwable $exception) {
            Notification::query()
                ->whereKey($notification->id)
                ->where('status', 'processing')
                ->update([
                    'status' => 'temporary_failure',
                    'last_reason_code' => 'audit_write_failed',
                    'next_attempt_at' => now()->addSeconds($this->backoff[0]),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }

        $notification->refresh();
        if ($notification->status === 'temporary_failure' && $notification->attempt_count < $this->tries) {
            throw new RuntimeException('Notification delivery requires retry.');
        }
    }

    private function resolveAttendeeName(PersonalDataCipher $cipher, Notification $notification): string
    {
        if ($notification->attendee_id === null || $notification->attendee_id === '') {
            return '';
        }

        $attendee = Attendee::query()
            ->where('tenant_id', $notification->tenant_id)
            ->whereKey($notification->attendee_id)
            ->first();

        if ($attendee === null) {
            return '';
        }

        try {
            $firstName = $cipher->decrypt([
                'key_id' => $attendee->encryption_key_id,
                'ciphertext' => $attendee->first_name_ciphertext,
            ], "{$attendee->tenant_id}:{$attendee->event_id}:attendee");

            $trimmed = trim($firstName);

            if ($trimmed === '' || in_array(strtolower($trimmed), ['guest', 'participant', 'attendee'], true)) {
                return '';
            }

            return $trimmed;
        } catch (Throwable) {
            return '';
        }
    }

    private function finish(
        Notification $notification,
        NotificationStatus $status,
        ?string $providerMessageId,
        ?string $reasonCode,
    ): void {
        $storedStatus = match ($status) {
            NotificationStatus::Accepted, NotificationStatus::Sent => 'sent',
            NotificationStatus::Delivered => 'delivered',
            NotificationStatus::PermanentFailure => 'permanent_failure',
            NotificationStatus::TemporaryFailure, NotificationStatus::Unknown => 'temporary_failure',
        };
        if ($storedStatus === 'temporary_failure' && $notification->attempt_count >= $this->tries) {
            $storedStatus = 'permanent_failure';
            $reasonCode = 'retry_exhausted';
        }
        DB::transaction(function () use ($notification, $storedStatus, $providerMessageId, $reasonCode): void {
            $notification->forceFill([
                'status' => $storedStatus,
                'provider_message_id' => $providerMessageId ?: $notification->provider_message_id,
                'last_reason_code' => $reasonCode,
                'next_attempt_at' => $storedStatus === 'temporary_failure'
                    ? now()->addSeconds($this->backoff[min($notification->attempt_count - 1, count($this->backoff) - 1)])
                    : null,
            ])->save();

            if (in_array($storedStatus, ['delivered', 'permanent_failure'], true)) {
                event(new NotificationTerminalStateReached(
                    $notification->tenant_id,
                    $notification->event_id,
                    $notification->id,
                    $storedStatus,
                    $notification->channel,
                    $reasonCode,
                ));
            }
        });
    }
}
