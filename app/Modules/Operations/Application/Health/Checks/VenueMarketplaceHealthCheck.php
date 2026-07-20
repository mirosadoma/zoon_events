<?php

namespace App\Modules\Operations\Application\Health\Checks;

use App\Modules\Operations\Application\Health\HealthCheckResult;
use App\Modules\Operations\Contracts\HealthCheck;
use App\Modules\Shared\Contracts\Clock;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\ControlDelegation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\MarketplaceDispute;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;
use Illuminate\Database\DatabaseManager;

final readonly class VenueMarketplaceHealthCheck implements HealthCheck
{
    private const ACTIVATION_LAG_THRESHOLD_MINUTES = 30;
    private const RELEASE_RETRY_THRESHOLD_MINUTES = 60;
    private const STATEMENT_LAG_THRESHOLD_HOURS = 24;
    private const DISPUTE_BACKLOG_CRITICAL_COUNT = 50;
    private const DISPUTE_AGE_CRITICAL_HOURS = 168; // 7 days

    public function __construct(
        private DatabaseManager $database,
        private Clock $clock,
    ) {}

    public function category(): string
    {
        return 'venue_marketplace';
    }

    public function run(): HealthCheckResult
    {
        $started = microtime(true);
        $now = $this->clock->now();
        $reasons = [];

        $conflictCount = AssetReservation::query()
            ->withoutGlobalScopes()
            ->where('status', 'reserved')
            ->where('reserved_from', '<=', $now)
            ->where('reserved_until', '>=', $now)
            ->groupBy('venue_asset_id', 'reserved_from', 'reserved_until')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($conflictCount > 0) {
            $reasons[] = 'approval_conflicts';
        }

        $activationLagCount = RentalRequest::query()
            ->withoutGlobalScopes()
            ->whereNotNull('approved_at')
            ->whereNull('activated_at')
            ->whereNull('cancelled_at')
            ->whereNull('revoked_at')
            ->where('requested_start_at', '<', $now->subMinutes(self::ACTIVATION_LAG_THRESHOLD_MINUTES))
            ->count();

        if ($activationLagCount > 0) {
            $reasons[] = 'activation_lag';
        }

        $degradedProvisioningCount = ControlDelegation::query()
            ->withoutGlobalScopes()
            ->where('status', 'degraded')
            ->whereNull('revoked_at')
            ->whereNull('completed_at')
            ->count();

        if ($degradedProvisioningCount > 0) {
            $reasons[] = 'degraded_provisioning';
        }

        $releaseRetryCount = ControlDelegation::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['active', 'degraded'])
            ->whereNull('revoked_at')
            ->whereNull('completed_at')
            ->where('ends_at', '<', $now->subMinutes(self::RELEASE_RETRY_THRESHOLD_MINUTES))
            ->count();

        if ($releaseRetryCount > 0) {
            $reasons[] = 'release_retries';
        }

        $lagCutoff = $now->subHours(self::STATEMENT_LAG_THRESHOLD_HOURS);

        $statementLagCount = RentalRequest::query()
            ->withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNotNull('completed_at')
                    ->orWhereNotNull('revoked_at');
            })
            ->where(function ($query) use ($lagCutoff) {
                $query->where('completed_at', '<', $lagCutoff)
                    ->orWhere('revoked_at', '<', $lagCutoff);
            })
            ->whereNotExists(function ($query) {
                $query->select($this->database->raw(1))
                    ->from('settlement_statements')
                    ->whereColumn('settlement_statements.rental_request_id', 'rental_requests.id');
            })
            ->count();

        if ($statementLagCount > 0) {
            $reasons[] = 'statement_lag';
        }

        $openDisputeCount = MarketplaceDispute::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['open', 'under_review'])
            ->count();

        $agedDisputeCount = MarketplaceDispute::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['open', 'under_review'])
            ->where('opened_at', '<', $now->subHours(self::DISPUTE_AGE_CRITICAL_HOURS))
            ->count();

        if ($openDisputeCount >= self::DISPUTE_BACKLOG_CRITICAL_COUNT || $agedDisputeCount > 0) {
            $reasons[] = 'dispute_backlog';
        }

        $status = $reasons === [] ? 'ok' : 'degraded';
        $reasonCode = $reasons === [] ? null : implode(',', $reasons);

        return new HealthCheckResult(
            $this->category(),
            $status,
            (int) round((microtime(true) - $started) * 1000),
            $reasonCode,
        );
    }
}
