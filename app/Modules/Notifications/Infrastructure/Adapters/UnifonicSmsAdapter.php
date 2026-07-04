<?php

namespace App\Modules\Notifications\Infrastructure\Adapters;

use App\Modules\Notifications\Contracts\NotificationAdapter;
use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationRequest;
use App\Modules\Notifications\Domain\NotificationResult;
use App\Modules\Notifications\Domain\NotificationStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class UnifonicSmsAdapter implements NotificationAdapter
{
    public function send(NotificationRequest $request): NotificationResult
    {
        if ($request->channel !== NotificationChannel::Sms) {
            return new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'channel_mismatch');
        }
        if (! (bool) config('notifications.allow_network', false)) {
            return new NotificationResult(NotificationStatus::TemporaryFailure, reasonCode: 'network_disabled');
        }

        $secretReference = (string) config('notifications.unifonic.app_sid_reference');
        $appSid = $secretReference !== '' ? getenv($secretReference) : false;
        if (! is_string($appSid) || $appSid === '') {
            return new NotificationResult(NotificationStatus::PermanentFailure, reasonCode: 'configuration_invalid');
        }

        try {
            $response = Http::timeout(max(1, (int) ceil(((int) config('notifications.timeout_ms', 5000)) / 1000)))
                ->asForm()
                ->withHeaders(['Idempotency-Key' => $request->idempotencyKey])
                ->post((string) config('notifications.unifonic.api_url'), [
                    'AppSid' => $appSid,
                    'SenderID' => $request->senderReference,
                    'Recipient' => $request->destination,
                    'Body' => strip_tags($request->body),
                ]);
        } catch (ConnectionException) {
            return new NotificationResult(NotificationStatus::TemporaryFailure, reasonCode: 'transport_unavailable');
        }

        if ($response->successful()) {
            return new NotificationResult(
                NotificationStatus::Accepted,
                (string) ($response->json('data.MessageID') ?? $response->json('MessageID') ?? ''),
            );
        }

        return new NotificationResult(
            $response->serverError() || $response->status() === 429
                ? NotificationStatus::TemporaryFailure
                : NotificationStatus::PermanentFailure,
            reasonCode: $response->status() === 429 ? 'rate_limited' : 'provider_rejected',
        );
    }
}
