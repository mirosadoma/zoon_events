<?php

namespace App\Modules\AdminConsole\ViewModels\Marketplace;

use App\Modules\VenueMarketplace\Application\Queries\GetParticipantStatementQuery;
use App\Modules\VenueMarketplace\Application\Queries\ListParticipantStatementsQuery;
use App\Modules\VenueMarketplace\Http\Resources\ParticipantStatementResource;

final readonly class TenantStatementViewModel
{
    public function __construct(
        private ListParticipantStatementsQuery $listQuery,
        private GetParticipantStatementQuery $getQuery,
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
            ($filters['dispute_status'] ?? null) ?: null,
            ($filters['cursor'] ?? null) ?: null,
            15,
        );

        return [
            'tenantId' => $tenantId,
            'statements' => ParticipantStatementResource::collection($page->items)->resolve(request()),
            'filters' => $this->normalizeFilters($filters),
            'pagination' => [
                'per_page' => $page->pageSize,
                'has_more' => $page->hasMore,
                'next_cursor' => $page->nextCursor,
            ],
            'actions' => $this->statementPermissions($permissions),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    public function show(string $tenantId, string $statementPublicId, array $permissions = []): array
    {
        $statement = $this->getQuery->execute((int) $tenantId, $statementPublicId);

        return [
            'tenantId' => $tenantId,
            'statementPublicId' => $statementPublicId,
            'statement' => (new ParticipantStatementResource($statement))->resolve(request()),
            'revisions' => [],
            'actions' => $this->statementPermissions($permissions),
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
    private function statementPermissions(array $overrides): array
    {
        $defaults = [
            'canExport' => false,
            'canOpenDispute' => false,
        ];

        return array_merge($defaults, $overrides);
    }
}
