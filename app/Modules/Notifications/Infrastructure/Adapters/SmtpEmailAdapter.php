<?php

namespace App\Modules\Notifications\Infrastructure\Adapters;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationResult;
use App\Modules\Notifications\Domain\NotificationStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SmtpEmailAdapter implements NotificationAdapter
{
    public function send(NotificationRequest $request): NotificationResult
    {
        if ($request->channel !== NotificationChannel::Email) {
            return new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'channel_mismatch');
        }
        if (! (bool) config('notifications.allow_network', false)
            && (string) config('mail.default') !== 'array'
            && (string) config('mail.default') !== 'log') {
            return new NotificationResult(NotificationStatus::TemporaryFailure, reasonCode: 'network_disabled');
        }

        try {
            Mail::html($request->body, function ($message) use ($request): void {
                $message->to($request->destination)
                    ->subject($request->subject);
                if ($request->senderReference !== '') {
                    $message->from($request->senderReference);
                }
                foreach ($request->embeddedImages as $image) {
                    $message->embedData($image->content, $image->contentId, $image->mimeType);
                }
            });

            return new NotificationResult(NotificationStatus::Sent, 'smtp-'.$request->notificationId);
        } catch (Throwable $exception) {
            Log::warning('notifications.smtp_delivery_failed', [
                'notification_id' => $request->notificationId,
                'reason' => $exception->getMessage(),
            ]);

            return new NotificationResult(NotificationStatus::TemporaryFailure, reasonCode: 'transport_unavailable');
        }
    }
}
