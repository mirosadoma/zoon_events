<?php

namespace App\Console\Commands;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupExpiredAuditExports extends Command
{
    protected $signature = 'zonetec:audit:cleanup-exports';

    protected $description = 'Expire audit exports and remove their private files.';

    public function handle(): int
    {
        AuditExport::query()->where('expires_at', '<=', now())->where('status', '!=', 'expired')->chunkById(100, function ($exports): void {
            foreach ($exports as $export) {
                if ($export->storage_path) {
                    Storage::disk('local')->delete($export->storage_path);
                }
                $export->update(['status' => 'expired', 'storage_path' => null]);
            }
        });

        return self::SUCCESS;
    }
}
