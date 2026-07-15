<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\GetParticipantRentalQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantRentalsQuery;
use App\Modules\VenueMarketplace\Application\Queries\RentalParticipantScope;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantRentalResource;

final readonly class TenantRentalViewModel
{
    public function __construct(
        private ListParticipantRentalsQuery $listQuery,
        private GetParticipantRentalQuery $getQuery,
        private RentalParticipantScope $participants,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function index(string $tenantId, array $filters = [], array $permissions = []): array
    {
        $page = $this->listQuery->execute(
            (int) $tenantId,
            ($filters['role'] ?? null) ?: null,
            ($filters['status'] ?? null) ?: null,
            ($filters['dispute_status'] ?? null) ?: null,
            ($filters['cursor'] ?? null) ?: null,
            15,
        );

        foreach ($page->items as $rental) {
            $rental->setAttribute('viewer_role', $this->participants->role((int) $tenantId, $rental));
        }

        return [
            'tenantId' => $tenantId,
            'rentals' => ParticipantRentalResource::collection($page->items)->resolve(request()),
            'filters' => $this->normalizeFilters($filters),
            'pagination' => [
                'per_page' => $page->pageSize,
                'has_more' => $page->hasMore,
                'next_cursor' => $page->nextCursor,
            ],
            'actions' => $this->rentalPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function show(string $tenantId, string $rentalPublicId, array $permissions = []): array
    {
        $rental = $this->getQuery->execute((int) $tenantId, $rentalPublicId);
        $rental->setAttribute('viewer_role', $this->participants->role((int) $tenantId, $rental));

        return [
            'tenantId' => $tenantId,
            'rentalPublicId' => $rentalPublicId,
            'rental' => (new ParticipantRentalResource($rental))->resolve(request()),
            'actions' => $this->rentalPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'role' => $filters['role'] ?? null,
            'status' => $filters['status'] ?? null,
            'venue_public_id' => $filters['venue_public_id'] ?? null,
            'event_id' => $filters['event_id'] ?? null,
            'dispute_status' => $filters['dispute_status'] ?? null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function rentalPermissions(array $overrides): array
    {
        $defaults = [
            'canApprove' => false,
            'canReject' => false,
            'canRevoke' => false,
            'canCancel' => false,
            'canViewDelegation' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
