<?php

namespace App\Modules\AdminConsole\ViewModels\Admin;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;

final readonly class AuditLogsViewModel
{
    /**
     * @param  list<AuditLog>  $logs
     * @param  array<string, mixed>  $filters
     * @return array{tenantId: string, filters: array<string, mixed>, auditLogs: list<array<string, mixed>>}
     */
    public function index(string $tenantId, array $filters, array $logs): array
    {
        return [
            'tenantId' => $tenantId,
            'filters' => $filters,
            'auditLogs' => array_map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor_id' => $log->actor_id,
                'action' => $log->action,
                'target_type' => $log->target_type,
                'target_id' => $log->target_id,
                'outcome' => $log->outcome,
                'metadata' => $log->metadata,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ], $logs),
        ];
    }
}
