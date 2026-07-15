<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\PlatformMarketplaceQuery;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantDisputeResource;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantRentalResource;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantStatementResource;

final readonly class PlatformMarketplaceViewModel
{
    public function __construct(private PlatformMarketplaceQuery $query) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function index(array $filters = [], array $permissions = []): array
    {
        $rentalPage = $this->query->listRentals(
            ($filters['status'] ?? null) ?: null,
            null,
            ($filters['cursor'] ?? null) ?: null,
            15,
        );

        return [
            'rentals' => ParticipantRentalResource::collection($rentalPage->items)->resolve(request()),
            'statements' => [],
            'disputes' => [],
            'filters' => $this->normalizeFilters($filters),
            'pagination' => [
                'per_page' => $rentalPage->pageSize,
                'has_more' => $rentalPage->hasMore,
                'next_cursor' => $rentalPage->nextCursor,
            ],
            'actions' => $this->platformPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function disputeShow(string $disputePublicId, array $permissions = []): array
    {
        $dispute = $this->query->getDispute($disputePublicId);

        return [
            'disputePublicId' => $disputePublicId,
            'dispute' => (new ParticipantDisputeResource($dispute))->withPlatformNotes()->resolve(request()),
            'actions' => $this->platformPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'status' => $filters['status'] ?? null,
            'owner_tenant_id' => $filters['owner_tenant_id'] ?? null,
            'organizer_tenant_id' => $filters['organizer_tenant_id'] ?? null,
            'venue_public_id' => $filters['venue_public_id'] ?? null,
            'event_id' => $filters['event_id'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function platformPermissions(array $overrides): array
    {
        $defaults = [
            'canView' => false,
            'canManageDisputes' => false,
            'canStartReview' => false,
            'canAddNote' => false,
            'canResolve' => false,
            'canReject' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
