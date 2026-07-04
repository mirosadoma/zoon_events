<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Contracts\NotificationDestinationAnonymizer;
use App\Modules\Notifications\Infrastructure\Persistence\Models\Notification;

final class DatabaseNotificationDestinationAnonymizer implements NotificationDestinationAnonymizer
{
    public function anonymizeForAttendee(string $tenantId, string $attendeeId, string $tombstone): void
    {
        Notification::query()->where('tenant_id', $tenantId)->where('attendee_id', $attendeeId)->update([
            'destination_ciphertext' => 'anonymized',
            'destination_index' => $tombstone,
            'encryption_key_id' => 'anonymized',
            'updated_at' => now(),
        ]);
    }
}
