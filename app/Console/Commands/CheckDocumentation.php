<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckDocumentation extends Command
{
    protected $signature = 'zonetec:docs:check';

    protected $description = 'Validate required Phase 0 documentation and governance metadata.';

    public function handle(): int
    {
        $required = [
            'docs/architecture/foundation.md', 'docs/architecture/module-boundaries.md',
            'docs/standards/tenant-isolation.md', 'docs/standards/rbac.md',
            'docs/standards/permission-catalog.md', 'docs/standards/audit-event-catalog.md',
            'docs/standards/data-classification.md', 'docs/standards/api.md',
            'docs/standards/dashboard-design-system.md', 'docs/standards/localization-accessibility.md',
            'docs/standards/adapter-authoring.md', 'docs/operations/configuration.md',
            'docs/operations/health-observability.md', 'docs/operations/queue-scheduler.md',
            'docs/operations/backup-restore.md', 'docs/operations/migrations-rollbacks.md',
            'docs/operations/retention-residency.md', 'docs/CONTRIBUTING.md',
            'docs/review-checklist.md', 'docs/governance/exceptions.md',
        ];
        $failures = [];
        foreach ($required as $path) {
            if (! File::exists(base_path($path)) || trim(File::get(base_path($path))) === '') {
                $failures[] = "Missing documentation: {$path}";
            }
        }

        $exceptions = File::get(base_path('docs/governance/exceptions.md'));
        preg_match_all('/Expiry or remediation date:\\s*(\\d{4}-\\d{2}-\\d{2}).*?Status:\\s*active/is', $exceptions, $matches);
        foreach ($matches[1] ?? [] as $date) {
            if (now()->startOfDay()->greaterThan($date)) {
                $failures[] = "Expired active governance exception: {$date}";
            }
        }

        foreach ($failures as $failure) {
            $this->components->error($failure);
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
