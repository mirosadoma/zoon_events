<?php

namespace App\Modules\AccessControl\Domain;

use App\Modules\AccessControl\Infrastructure\Persistence\Models\AcsLane;
use DateTimeInterface;

final readonly class AcsLaneHealthDeriver
{
    public function derive(AcsLane $lane, int $thresholdSeconds, DateTimeInterface $now): string
    {
        if ($lane->last_seen_at === null) {
            return 'offline';
        }

        $nowTs = (int) $now->getTimestamp();
        $seenTs = (int) $lane->last_seen_at->getTimestamp();

        if (($nowTs - $seenTs) > $thresholdSeconds) {
            return 'offline';
        }

        return $lane->health_status === 'offline' ? 'online' : $lane->health_status;
    }
}
