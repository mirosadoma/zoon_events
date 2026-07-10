<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Queries\SearchAuditLogs;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly TenantContextStore $tenantContextStore,
        private readonly SearchAuditLogs $search,
    ) {}

    public function platform(Request $request)
    {
        /** @var User $actor */
        $actor = $request->user();
        $page = $this->search->platform($request->only(['from', 'to', 'action', 'outcome', 'actor_id', 'cursor', 'page_size']));
        $this->audit->writePlatform('audit.searched', 'succeeded', $actor, metadata: ['scope' => 'platform']);

        return $this->success(
            collect($page->items)->map(fn (AuditLog $log): array => $this->mapLog($log))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    public function tenant(Request $request)
    {
        $context = $this->tenantContextStore->current();
        $page = $this->search->tenant($context->tenant->id, $request->only(['from', 'to', 'action', 'outcome', 'actor_id', 'cursor', 'page_size']));

        $this->audit->writeTenant('audit.searched', 'succeeded', $context, metadata: ['scope' => 'tenant']);

        return $this->success(
            collect($page->items)->map(fn (AuditLog $log): array => $this->mapLog($log))->all(),
            meta: ['page_size' => $page->pageSize, 'has_more' => $page->hasMore, 'next_cursor' => $page->nextCursor],
        );
    }

    private function mapLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'scope' => $log->scope,
            'tenant_id' => $log->tenant_id,
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'action' => $log->action,
            'target_type' => $log->target_type,
            'target_id' => $log->target_id,
            'outcome' => $log->outcome,
            'reason_code' => $log->reason_code,
            'channel' => $log->channel,
            'correlation_id' => $log->correlation_id,
            'metadata' => $log->metadata,
            'occurred_at' => $log->occurred_at?->toIso8601String(),
            'integrity_key_id' => $log->integrity_key_id,
        ];
    }
}
