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
            'docs/standards/phase1-registration-ticketing.md',
            'docs/operations/phase1-data-governance.md',
            'docs/operations/payments.md',
            'docs/operations/credential-keys.md',
            'docs/operations/notifications.md',
            'docs/operations/offline-scanning-design.md',
            'docs/operations/wallet-adapter-runbook.md',
            'docs/operations/check-in-runbook.md',
            'docs/operations/acs-integration-protocol.md',
            'docs/operations/acs-config-runbook.md',
            'docs/operations/acs-emergency-egress-runbook.md',
            'docs/security/permissions-phase4.md',
            'docs/security/audit-catalog-phase4.md',
            'docs/release/phase1-migration-evidence.md',
            'docs/release/phase1-dependency-audit.md',
            'docs/release/phase1-readiness.md',
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
