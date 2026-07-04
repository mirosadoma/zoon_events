<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Domain\Events\NotificationTerminalStateReached;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;
use Illuminate\Support\Facades\DB;

final class ProcessNotificationCallback
{
    public function handle(string $providerMessageId, string $providerStatus): bool
    {
        return DB::transaction(function () use ($providerMessageId, $providerStatus): bool {
            $notification = Notification::query()->where('provider_message_id', $providerMessageId)
                ->lockForUpdate()->first();
            if (! $notification) {
                return false;
            }
            if (in_array($notification->status, ['delivered', 'permanent_failure'], true)) {
                return true;
            }

            $status = match (strtolower($providerStatus)) {
                'delivered', 'delivery_success' => 'delivered',
                'failed', 'undelivered', 'rejected' => 'permanent_failure',
                default => null,
            };
            if ($status === null || $notification->status === $status) {
                return true;
            }

            $notification->forceFill([
                'status' => $status,
                'last_reason_code' => $status === 'permanent_failure' ? 'provider_terminal_failure' : null,
                'next_attempt_at' => null,
            ])->save();
            event(new NotificationTerminalStateReached(
                $notification->tenant_id,
                $notification->event_id,
                $notification->id,
                $status,
                $notification->channel,
                $notification->last_reason_code,
            ));

            return true;
        });
    }
}
