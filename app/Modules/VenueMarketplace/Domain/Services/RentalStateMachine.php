<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;
use DateTimeImmutable;

final class RentalStateMachine
{
    private const MAX_REASON_LENGTH = 2000;

    private const TRANSITIONS = [
        'requested' => [
            'approved' => 'owner',
            'rejected' => 'owner',
            'cancelled' => 'organizer',
        ],
        'approved' => [
            'active' => 'system',
            'cancelled' => 'organizer',
            'revoked' => 'owner',
        ],
        'active' => [
            'completed' => 'system',
            'revoked' => 'owner',
        ],
        'rejected' => [],
        'cancelled' => [],
        'revoked' => [],
        'completed' => [],
    ];

    public function transition(
        string $current,
        string $target,
        string $actorType,
        ?string $reason,
        int $expectedVersion,
        int $currentVersion,
        DateTimeImmutable $transitionedAt,
    ): string {
        if ($expectedVersion < 1 || $currentVersion < 1 || $expectedVersion !== $currentVersion) {
            $this->deny();
        }

        $requiredActor = self::TRANSITIONS[$current][$target] ?? null;

        if ($requiredActor === null || $requiredActor !== $actorType) {
            $this->deny();
        }

        if (in_array($target, ['rejected', 'revoked'], true)) {
            $normalizedReason = trim((string) $reason);

            if ($normalizedReason === '' || mb_strlen($normalizedReason) > self::MAX_REASON_LENGTH) {
                $this->deny();
            }
        }

        return $target;
    }

    private function deny(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_RENTAL_STATE_CONFLICT);
    }
}
