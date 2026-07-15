<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;

final class MarketplaceDisputeStateMachine
{
    private const MAX_TEXT_LENGTH = 4000;

    private const TRANSITIONS = [
        'open' => [
            'under_review' => 'platform',
            'resolved' => 'platform',
            'rejected' => 'platform',
        ],
        'under_review' => [
            'resolved' => 'platform',
            'rejected' => 'platform',
        ],
        'resolved' => [],
        'rejected' => [],
    ];

    public function transition(
        string $current,
        string $target,
        string $actorScope,
    ): string {
        $requiredScope = self::TRANSITIONS[$current][$target] ?? null;

        if ($requiredScope === null || $requiredScope !== $actorScope) {
            $this->deny();
        }

        return $target;
    }

    public function canTransition(string $current, string $target, string $actorScope): bool
    {
        return (self::TRANSITIONS[$current][$target] ?? null) === $actorScope;
    }

    public function assertValidReason(string $reason): void
    {
        $normalized = trim($reason);
        if ($normalized === '' || mb_strlen($normalized) > self::MAX_TEXT_LENGTH) {
            $this->deny();
        }
    }

    public function assertValidReasonCode(string $reasonCode): void
    {
        $normalized = trim($reasonCode);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            $this->deny();
        }
    }

    public function assertValidNote(string $note): void
    {
        $normalized = trim($note);
        if ($normalized === '' || mb_strlen($normalized) > self::MAX_TEXT_LENGTH) {
            $this->deny();
        }
    }

    public function assertValidVisibility(string $visibility): void
    {
        if (! in_array($visibility, ['participants', 'platform_only'], true)) {
            $this->deny();
        }
    }

    private function deny(): never
    {
        throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT);
    }
}
