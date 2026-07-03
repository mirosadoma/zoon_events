<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Jobs\GenerateAuditExportJob;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditExport;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Contracts\Queue\TenantJobContext;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class AuditExportController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly AuditWriter $audit,
    ) {}

    public function store(Request $request)
    {
        $context = $this->contexts->current();
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'action' => ['nullable', 'string', 'max:160'],
            'outcome' => ['nullable', 'in:succeeded,denied,failed'],
            'format' => ['required', 'in:csv'],
        ]);

        $export = DB::transaction(function () use ($context, $validated): AuditExport {
            $export = AuditExport::query()->create([
                'scope' => 'tenant',
                'tenant_id' => $context->tenant->id,
                'requested_by_user_id' => $context->actor->id,
                'filters' => $validated,
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) config('audit.export_expiry_minutes', 60)),
            ]);
            $this->audit->writeTenant('audit.export_requested', 'succeeded', $context, targetType: 'audit_export', targetId: $export->id);

            return $export;
        });
        GenerateAuditExportJob::dispatch($export->id, new TenantJobContext($context->tenant->id, $context->membership->id, $context->actor->id));

        return $this->success($this->map($export), 202);
    }

    public function show(string $exportId)
    {
        $context = $this->contexts->current();
        $export = AuditExport::query()->where('tenant_id', $context->tenant->id)->findOrFail($exportId);

        return $this->success($this->map($export));
    }

    public function download(string $exportId)
    {
        $context = $this->contexts->current();
        $export = AuditExport::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('status', 'completed')
            ->where('expires_at', '>', now())
            ->findOrFail($exportId);

        abort_unless($export->storage_path && Storage::disk('local')->exists($export->storage_path), 404);
        $this->audit->writeTenant('audit.export_downloaded', 'succeeded', $context, targetType: 'audit_export', targetId: $export->id);

        return Storage::disk('local')->download($export->storage_path, "audit-export-{$export->id}.csv", ['Content-Type' => 'text/csv']);
    }

    private function map(AuditExport $export): array
    {
        return [
            'id' => $export->id,
            'status' => $export->status,
            'record_count' => $export->record_count,
            'failure_code' => $export->failure_code,
            'expires_at' => $export->expires_at?->toIso8601String(),
            'created_at' => $export->created_at?->toIso8601String(),
        ];
    }
}
