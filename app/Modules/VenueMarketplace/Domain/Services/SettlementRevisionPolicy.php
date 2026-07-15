<?php

namespace App\Modules\VenueMarketplace\Domain\Services;

use App\Modules\VenueMarketplace\Domain\Exceptions\MarketplaceDomainException;
use App\Modules\VenueMarketplace\Http\Problems\Phase6Problem;

final class SettlementRevisionPolicy
{
    public function assertCanRevise(string $currentStatus, int $currentRevision): void
    {
        if ($currentStatus !== 'issued') {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY);
        }
    }

    public function nextRevision(int $currentRevision): int
    {
        return $currentRevision + 1;
    }

    public function assertDisputeLinked(string $disputeStatus): void
    {
        if (! in_array($disputeStatus, ['open', 'under_review', 'resolved'], true)) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_DISPUTE_STATE_CONFLICT);
        }
    }

    /**
     * @param list<array{unit_price_minor: int, billable_units: int}> $lines
     */
    public function computeTotal(array $lines): int
    {
        $total = 0;
        foreach ($lines as $line) {
            $lineTotal = $line['unit_price_minor'] * $line['billable_units'];
            if ($lineTotal < 0) {
                throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY);
            }
            $total += $lineTotal;
        }

        return $total;
    }

    public function assertReasonCode(string $reasonCode): void
    {
        $normalized = trim($reasonCode);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new MarketplaceDomainException(Phase6Problem::MARKETPLACE_STATEMENT_NOT_READY);
        }
    }
}
