<?php

namespace App\Modules\Kiosk\Application;

use App\Modules\Kiosk\Contracts\KioskFleetSummaryProvider;
use App\Modules\Kiosk\Domain\KioskStatusDeriver;
use App\Modules\Kiosk\Infrastructure\Persistence\Models\Kiosk;
use App\Modules\Shared\Contracts\Clock;

final readonly class KioskFleetSummaryService implements KioskFleetSummaryProvider
{
    public function __construct(
        private KioskStatusDeriver $deriver,
        private Clock $clock,
    ) {}

    public function summarize(): array
    {
        $now = $this->clock->now();
        $defaultThreshold = (int) config('printing.kiosk.default_offline_threshold_seconds', 120);

        $counts = ['online' => 0, 'offline' => 0, 'degraded' => 0, 'retired' => 0, 'pending' => 0];

        Kiosk::query()->each(function (Kiosk $kiosk) use (&$counts, $defaultThreshold, $now): void {
            $status = $this->deriver->derive($kiosk, $defaultThreshold, $now);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        });

        return $counts;
    }
}
