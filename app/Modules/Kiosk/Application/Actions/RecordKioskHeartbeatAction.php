<?php

namespace App\Modules\Kiosk\Application\Actions;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;

final readonly class RecordKioskHeartbeatAction
{
    public function execute(
        Kiosk $kiosk,
        string $printerStatus,
        ?string $printerReasonCode,
        ?string $appVersion,
    ): void {
        $kiosk->forceFill([
            'last_heartbeat_at' => now(),
            'printer_status'    => $printerStatus,
        ])->save();
    }
}
