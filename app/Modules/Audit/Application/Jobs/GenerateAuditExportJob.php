<?php

namespace App\Modules\Audit\Application\Jobs;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditExport;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Tenancy\Application\Queue\RestoreTenantContext;
use App\Modules\Tenancy\Contracts\Queue\TenantAwareJob;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateAuditExportJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $exportId,
        private readonly TenantJobContext $context,
    ) {
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

    public function handle(): void
    {
        $export = AuditExport::query()->where('tenant_id', $this->context->tenantId)->findOrFail($this->exportId);

        $claimed = AuditExport::query()
            ->whereKey($export->id)
            ->where('tenant_id', $this->context->tenantId)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'processing', 'started_at' => now(), 'failure_code' => null]);

        if ($claimed !== 1) {
            return;
        }

        $export->refresh();

        try {
            $path = "tenants/{$export->tenant_id}/audit-exports/{$export->id}.csv";
            $stream = fopen('php://temp', 'w+b');
            fputcsv($stream, ['id', 'occurred_at', 'actor_type', 'actor_id', 'action', 'target_type', 'target_id', 'outcome', 'reason_code', 'correlation_id']);
            $count = 0;
            AuditLog::query()
                ->where('scope', 'tenant')
                ->where('tenant_id', $export->tenant_id)
                ->where('occurred_at', '>=', $export->filters['from'])
                ->where('occurred_at', '<', $export->filters['to'])
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->cursor()
                ->each(function (AuditLog $log) use ($stream, &$count): void {
                    fputcsv($stream, [$log->id, $log->occurred_at?->toIso8601String(), $log->actor_type, $log->actor_id, $log->action, $log->target_type, $log->target_id, $log->outcome, $log->reason_code, $log->correlation_id]);
                    $count++;
                });
            rewind($stream);
            Storage::disk('local')->put($path, $stream);
            fclose($stream);
            $export->update(['status' => 'completed', 'storage_path' => $path, 'record_count' => $count, 'completed_at' => now()]);
        } catch (Throwable $throwable) {
            report($throwable);
            $export->update(['status' => 'failed', 'failure_code' => 'export_generation_failed', 'completed_at' => now()]);
            throw $throwable;
        }
    }
}
