<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditWriter;
use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;

final class AuditVerificationController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly AuditIntegrityService $integrity,
        private readonly AuditWriter $audit,
    ) {}

    public function __invoke(Request $request)
    {
        $context = $this->contexts->current();
        $validated = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from']]);
        $failed = 0;
        $verified = 0;

        AuditLog::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('occurred_at', '>=', $validated['from'])
            ->where('occurred_at', '<', $validated['to'])
            ->limit(10000)
            ->each(function (AuditLog $log) use (&$failed, &$verified): void {
                $verified++;
                if (! $this->integrity->verify($this->payload($log), $log->integrity_key_id, $log->integrity_hash)) {
                    $failed++;
                }
            });

        $this->audit->writeTenant($failed ? 'audit.integrity_failed' : 'audit.integrity_verified', $failed ? 'failed' : 'succeeded', $context, metadata: ['verified_count' => $verified, 'failed_count' => $failed]);

        return $this->success(['verified_count' => $verified, 'failed_count' => $failed, 'status' => $failed ? 'invalid' : 'valid']);
    }

    private function payload(AuditLog $log): array
    {
        return collect($log->getAttributes())
            ->except(['id', 'integrity_hash', 'created_at'])
            ->map(fn ($value, $key) => in_array($key, ['metadata', 'change_summary'], true) && is_string($value) ? json_decode($value, true) : $value)
            ->all();
    }
}
