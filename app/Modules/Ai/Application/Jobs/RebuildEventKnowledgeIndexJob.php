<?php

namespace App\Modules\Ai\Application\Jobs;

use App\Modules\Ai\Application\Actions\RebuildEventIndex;
use App\Modules\Tenancy\Application\Queue\RestoreTenantContext;
use App\Modules\Tenancy\Contracts\Queue\TenantAwareJob;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RebuildEventKnowledgeIndexJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $eventId,
        private readonly TenantJobContext $context,
    ) {
        $this->queue = 'foundation';
        $this->afterCommit();
    }

    public function tenantJobContext(): TenantJobContext
    {
        return $this->context;
    }

    public function middleware(): array
    {
        return [app(RestoreTenantContext::class)];
    }

    public function handle(RebuildEventIndex $action): void
    {
        $action->execute((int) $this->context->tenantId, $this->eventId);
    }

    public function uniqueId(): string
    {
        return "rebuild_knowledge_{$this->context->tenantId}_{$this->eventId}";
    }

    public function shouldBeUnique(): bool
    {
        return true;
    }
}
