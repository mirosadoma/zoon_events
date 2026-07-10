<?php

namespace App\Modules\Audit\Application\Queries;

use App\Exceptions\FoundationException;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class SearchAuditLogs
{
    public function __construct(private readonly CursorPaginator $paginator) {}

    /** @param array<string, mixed> $filters */
    public function tenant(string $tenantId, array $filters): CursorPage
    {
        return $this->search(
            AuditLog::query()->where('scope', 'tenant')->where('tenant_id', $tenantId),
            "tenant:{$tenantId}:audit",
            $filters,
        );
    }

    /** @param array<string, mixed> $filters */
    public function platform(array $filters): CursorPage
    {
        return $this->search(
            AuditLog::query()->where('scope', 'platform')->whereNull('tenant_id'),
            'platform:audit',
            $filters,
        );
    }

    /** @param array<string, mixed> $filters */
    private function search(Builder $query, string $scope, array $filters): CursorPage
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $from = CarbonImmutable::parse($filters['from'] ?? $today->subDays(7));
        $to = CarbonImmutable::parse($filters['to'] ?? $today->addDay());

        if ($to <= $from || $from->diffInDays($to) > (int) config('audit.max_search_days', 31)) {
            throw FoundationException::validation('audit_range_invalid', 'Audit searches require a valid bounded date range.');
        }

        $query = $query
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to)
            ->when($filters['action'] ?? null, fn (Builder $builder, string $action) => $builder->where('action', $action))
            ->when($filters['outcome'] ?? null, fn (Builder $builder, string $outcome) => $builder->where('outcome', $outcome))
            ->when($filters['actor_id'] ?? null, fn (Builder $builder, string $actorId) => $builder->where('actor_id', $actorId));

        $boundFilters = [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'action' => $filters['action'] ?? null,
            'outcome' => $filters['outcome'] ?? null,
            'actor_id' => $filters['actor_id'] ?? null,
        ];

        return $this->paginator->paginate(
            $query,
            $scope,
            $boundFilters,
            $filters['cursor'] ?? null,
            (int) ($filters['page_size'] ?? 50),
            'occurred_at',
        );
    }
}
