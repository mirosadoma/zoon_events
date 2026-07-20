<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use Illuminate\Support\Facades\DB;

final class InviteCodeGenerator
{
    public function generateUnique(int|string $eventId): string
    {
        return DB::transaction(function () use ($eventId): string {
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $code = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);

                $exists = EventRegistrationInvite::query()
                    ->where('event_id', $eventId)
                    ->where('code', $code)
                    ->exists();

                if (! $exists) {
                    return $code;
                }
            }

            throw new \RuntimeException('Unable to generate a unique invite code.');
        });
    }
}
