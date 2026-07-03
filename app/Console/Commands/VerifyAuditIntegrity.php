<?php

namespace App\Console\Commands;

use App\Modules\Audit\Application\Integrity\AuditIntegrityService;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Console\Command;

final class VerifyAuditIntegrity extends Command
{
    protected $signature = 'zonetec:audit:verify {--recent} {--limit=10000}';

    protected $description = 'Verify bounded audit-record HMAC integrity.';

    public function handle(AuditIntegrityService $integrity): int
    {
        $failed = 0;
        $query = AuditLog::query()->orderByDesc('occurred_at')->limit(min((int) $this->option('limit'), 100000));
        if ($this->option('recent')) {
            $query->where('occurred_at', '>=', now()->subDay());
        }

        $query->each(function (AuditLog $log) use ($integrity, &$failed): void {
            if (! $integrity->verify($log->integrityPayload(), $log->integrity_key_id, $log->integrity_hash)) {
                $failed++;
            }
        });

        $this->line("Audit verification failures: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
