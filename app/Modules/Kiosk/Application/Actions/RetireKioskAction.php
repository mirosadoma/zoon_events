<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\Kiosk\Domain\Events\KioskRetired;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\KioskSession;

final readonly class RetireKioskAction
{
    public function execute(Kiosk $kiosk): void
    {
        KioskSession::query()
            ->where('tenant_id', $kiosk->tenant_id)
            ->where('kiosk_id', $kiosk->id)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (KioskSession $s) => $s->forceFill(['revoked_at' => now()])->save());

        $kiosk->forceFill(['status' => 'retired', 'retired_at' => now()])->save();

        event(new KioskRetired($kiosk->tenant_id, $kiosk->event_id, $kiosk->id));
    }
}
