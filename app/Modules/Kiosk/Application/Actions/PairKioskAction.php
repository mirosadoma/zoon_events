<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\Kiosk\Domain\Events\KioskPaired;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final readonly class PairKioskAction
{
    /**
     * @return array{secret: string, expiresAt: DateTimeInterface}
     */
    public function execute(Kiosk $kiosk): array
    {
        $ttlHours = (int) config('printing.kiosk.session_ttl_hours', 168);

        // 8-digit numeric pairing code (zero-padded). Easy to type into the kiosk app.
        $rawSecret = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);

        $expiresAt = now()->addHours($ttlHours);

        $session = DB::transaction(function () use ($kiosk, $rawSecret, $expiresAt): KioskSession {
            KioskSession::query()
                ->where('tenant_id', $kiosk->tenant_id)
                ->where('kiosk_id', $kiosk->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (KioskSession $s) => $s->forceFill(['revoked_at' => now()])->save());

            $session = KioskSession::create([
                'tenant_id' => $kiosk->tenant_id,
                'kiosk_id' => $kiosk->id,
                'secret_hash' => hash('sha256', $rawSecret),
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);

            event(new KioskPaired($kiosk->tenant_id, $kiosk->event_id, $kiosk->id, $session->id));

            return $session;
        });

        return ['secret' => $rawSecret, 'expiresAt' => $expiresAt->toDateTimeImmutable()];
    }
}
