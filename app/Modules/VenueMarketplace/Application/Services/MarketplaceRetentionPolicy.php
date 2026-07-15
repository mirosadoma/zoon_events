<?php

namespace App\Modules\VenueMarketplace\Application\Services;

use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\SettlementStatement;
use Illuminate\Support\Carbon;

final readonly class MarketplaceRetentionPolicy
{
    public function statementRetentionDays(): int
    {
        return (int) config('marketplace.retention.statement_days', 2555);
    }

    public function disputeRetentionDays(): int
    {
        return (int) config('marketplace.retention.dispute_days', 2555);
    }

    public function auditRetentionDays(): int
    {
        return (int) config('marketplace.retention.audit_days', 2555);
    }

    public function isStatementRetained(SettlementStatement $statement): bool
    {
        if ($statement->isIssued()) {
            return true;
        }

        $cutoff = Carbon::now()->subDays($this->statementRetentionDays());

        return $statement->created_at->isAfter($cutoff);
    }

    public function isDisputeRetained(MarketplaceDispute $dispute): bool
    {
        if ($dispute->isActive()) {
            return true;
        }

        $cutoff = Carbon::now()->subDays($this->disputeRetentionDays());

        return $dispute->created_at->isAfter($cutoff);
    }

    public function canMinimizeEvidence(MarketplaceDispute $dispute): bool
    {
        if ($dispute->isActive()) {
            return false;
        }

        $cutoff = Carbon::now()->subDays($this->disputeRetentionDays());

        return $dispute->resolved_at !== null
            && $dispute->resolved_at->isBefore($cutoff);
    }
}
