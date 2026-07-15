<?php

namespace App\Modules\VenueMarketplace\Application\Queries;

use App\Modules\VenueMarketplace\Domain\Services\RentalStateMachine;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\AssetReservation;
use App\Modules\VenueMarketplace\Infrastructure\Persistence\Models\RentalRequest;

final readonly class RentalDecisionContextQuery
{
    public function __construct(
        private RentalParticipantScope $scope,
        private RentalStateMachine $states,
    ) {}

    /**
     * @return array{
     *     can_approve: bool,
     *     can_reject: bool,
     *     can_cancel: bool,
     *     can_revoke: bool,
     *     expected_version: int,
     *     conflict_summary: ?array<string, mixed>,
     *     timeline: list<array{action: string, at: string}>,
     * }
     */
    public function execute(int $tenantId, RentalRequest $rental): array
    {
        $role = $this->scope->role($tenantId, $rental);
        $status = $rental->status;
        $version = (int) $rental->version;

        $canApprove = $role === 'owner' && $this->states->canTransition($status, 'approved', 'owner');
        $canReject = $role === 'owner' && $this->states->canTransition($status, 'rejected', 'owner');
        $canCancel = $role === 'organizer' && $this->states->canTransition($status, 'cancelled', 'organizer');
        $canRevoke = $role === 'owner' && $this->states->canTransition($status, 'revoked', 'owner');

        $conflictSummary = null;
        if ($canApprove) {
            $conflictCount = AssetReservation::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $rental->tenant_id)
                ->whereIn('venue_asset_id', $rental->assets->pluck('venue_asset_id'))
                ->blocking()
                ->where('reserved_from', '<', $rental->requested_end_at)
                ->where('reserved_until', '>', $rental->requested_start_at)
                ->count();

            if ($conflictCount > 0) {
                $conflictSummary = ['conflicting_assets' => $conflictCount];
            }
        }

        $timeline = collect();
        if ($rental->submitted_at) {
            $timeline->push(['action' => 'submitted', 'at' => (string) $rental->submitted_at]);
        }
        if ($rental->approved_at) {
            $timeline->push(['action' => 'approved', 'at' => (string) $rental->approved_at]);
        }
        if ($rental->rejected_at) {
            $timeline->push(['action' => 'rejected', 'at' => (string) $rental->rejected_at]);
        }
        if ($rental->cancelled_at) {
            $timeline->push(['action' => 'cancelled', 'at' => (string) $rental->cancelled_at]);
        }
        if ($rental->revoked_at) {
            $timeline->push(['action' => 'revoked', 'at' => (string) $rental->revoked_at]);
        }
        if ($rental->activated_at) {
            $timeline->push(['action' => 'activated', 'at' => (string) $rental->activated_at]);
        }
        if ($rental->completed_at) {
            $timeline->push(['action' => 'completed', 'at' => (string) $rental->completed_at]);
        }

        return [
            'can_approve' => $canApprove,
            'can_reject' => $canReject,
            'can_cancel' => $canCancel,
            'can_revoke' => $canRevoke,
            'expected_version' => $version,
            'conflict_summary' => $conflictSummary,
            'timeline' => $timeline->sortBy('at')->values()->all(),
        ];
    }
}
