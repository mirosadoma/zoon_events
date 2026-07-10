<?php

namespace App\Modules\Kiosk\Domain;

use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use DateTimeInterface;

final readonly class KioskStatusDeriver
{
    public function derive(Kiosk $kiosk, int $thresholdSeconds, DateTimeInterface $now): string
    {
        if ($kiosk->status === 'retired') {
            return 'retired';
        }

        if ($kiosk->printer_status === 'error') {
            return 'degraded';
        }

        if ($kiosk->last_heartbeat_at === null) {
            return 'offline';
        }

        $nowTs = (int) $now->getTimestamp();
        $heartbeatTs = (int) $kiosk->last_heartbeat_at->getTimestamp();

        if (($nowTs - $heartbeatTs) > $thresholdSeconds) {
            return 'offline';
        }

        return 'online';
    }
}
