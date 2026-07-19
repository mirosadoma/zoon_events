<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Infrastructure\Persistence\Models\EventRegistrationInvite;
use Illuminate\Support\Facades\DB;

final readonly class DeactivateRegistrationInvite
{
    public function execute(int|string $eventId, string $email, ?string $code = null): void
    {
        DB::transaction(function () use ($eventId, $email, $code): void {
            $query = EventRegistrationInvite::query()
                ->where('event_id', $eventId)
                ->where('email', strtolower(trim($email)))
                ->where('is_active', true);

            if ($code !== null && $code !== '') {
                $query->where('code', $code);
            }

            $query->update([
                'is_active' => false,
                'used_at' => now(),
                'invite_status' => 'registered',
            ]);
        });
    }
}
