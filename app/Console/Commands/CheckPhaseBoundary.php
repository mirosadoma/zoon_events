<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckPhaseBoundary extends Command
{
    protected $signature = 'zonetec:phase-boundary:check';

    protected $description = 'Reject container files and product implementation beyond the active Phase 5 scope.';

    /** @var list<string> */
    private array $allowedProductPaths = [
        'app/Modules/WalletPasses',
        'app/Modules/Scanning',
        'app/Modules/Kiosk',
        'app/Modules/BadgePrinting',
        'app/Modules/AccessControl',
        'app/Modules/IdentityVerification',
        'app/Modules/AdminConsole',
        'app/Modules/Authorization/Policies/Phase2',
        'app/Modules/Authorization/Policies/Phase3',
        'app/Modules/Authorization/Policies/Phase4',
        'app/Modules/Authorization/Policies/Phase5',
        'app/Modules/Audit/Application/Listeners/Phase3',
        'app/Modules/Audit/Application/Listeners/Phase4',
        'app/Modules/Audit/Application/Listeners/Phase5',
        'app/Modules/Operations/Application/Health/Checks/AppleWalletHealthCheck.php',
        'app/Modules/Operations/Application/Health/Checks/GoogleWalletHealthCheck.php',
        'app/Modules/Operations/Application/Health/Checks/KioskFleetHealthCheck.php',
        'app/Modules/Operations/Application/Health/HealthService.php',
        'app/Modules/Shared/Http/Middleware/RequireIdempotencyKey.php',
        'app/Modules/Shared/Http/Problems/Phase3Problem.php',
        'app/Modules/Shared/Http/Problems/Phase4Problem.php',
        'app/Providers/ModuleServiceProvider.php',
        'resources/js/pages/tenant/checkin',
        'resources/js/pages/tenant/kiosk',
        'resources/js/pages/tenant/manual-desk',
        'resources/js/pages/tenant/badge-templates',
        'resources/js/pages/tenant/gate-events',
        'resources/js/pages/tenant/acs',
        'resources/js/pages/tenant/acs-health',
        'resources/js/pages/tenant/identity',
        'resources/js/pages/admin',
        'resources/js/pages/tenant/reports',
        'resources/js/navigation',
        'resources/js/lib/tenant-navigation.ts',
        'resources/js/pages/kiosk',
        'resources/js/components/wallet',
        'resources/js/components/checkin',
        'resources/js/components/kiosk',
        'resources/js/components/manual-desk',
        'resources/js/components/badge-templates',
        'resources/js/components/gate-events',
        'resources/js/components/acs',
        'resources/js/components/acs-health',
        'resources/js/components/identity',
        'resources/js/types/phase2.ts',
        'resources/js/types/phase3.ts',
        'resources/js/types/phase4.ts',
        'resources/js/types/phase5.ts',
        'tests/Feature/WalletPasses',
        'tests/Feature/Scanning',
        'tests/Feature/Kiosk',
        'tests/Feature/BadgePrinting',
        'tests/Feature/AccessControl',
        'tests/Feature/IdentityVerification',
        'tests/Contract/Phase2',
        'tests/Contract/Phase3',
        'tests/Contract/Phase4',
        'tests/Contract/Wallet',
        'tests/Browser/Phase2',
        'tests/Architecture/Phase2ModuleBoundaryTest.php',
        'tests/Architecture/Phase3ModuleBoundaryTest.php',
        'tests/Architecture/Phase4ModuleBoundaryTest.php',
        'tests/Architecture/Phase5ModuleBoundaryTest.php',
        'tests/Feature/Authorization/Phase2PermissionMatrixTest.php',
        'tests/Feature/Authorization/Phase4PermissionMatrixTest.php',
        'tests/Feature/Authorization/Phase5PermissionMatrixTest.php',
        'tests/Support/Phase2MySqlTestCase.php',
        'tests/Support/Phase3MySqlTestCase.php',
        'tests/Support/Phase4MySqlTestCase.php',
        'tests/Support/Phase5MySqlTestCase.php',
        'tests/Support/CreatesPhase4AcsFixture.php',
        'lang/en/phase2.php',
        'lang/ar/phase2.php',
        'lang/en/phase3.php',
        'lang/ar/phase3.php',
        'lang/en/phase4.php',
        'lang/ar/phase4.php',
        'lang/en/phase5.php',
        'lang/ar/phase5.php',
        'config/wallet.php',
        'config/acs.php',
        'config/printing.php',
        'config/identity-verification.php',
        'database/migrations/2026_07_07_000001_create_acs_integration_credentials_table.php',
        'database/migrations/2026_07_07_000002_add_acs_gate_scanner_type.php',
        'database/migrations/2026_07_07_000003_create_acs_zones_table.php',
        'database/migrations/2026_07_07_000004_create_acs_lanes_table.php',
        'database/migrations/2026_07_07_000005_create_acs_authorization_rules_table.php',
        'database/migrations/2026_07_07_0000055_add_scan_events_scope_unique.php',
        'database/migrations/2026_07_07_000006_create_access_events_table.php',
        'database/migrations/2026_07_07_000007_fix_idempotency_actor_id_for_integration_auth.php',
        'database/migrations/2026_07_07_000008_create_anti_passback_states_table.php',
        'database/migrations/2026_07_07_000009_create_emergency_events_table.php',
        'database/migrations/2026_07_08_000001_create_identity_verification_requirements_table.php',
        'database/migrations/2026_07_08_000002_create_identity_verifications_table.php',
        'database/migrations/2026_07_08_000003_create_identity_consents_table.php',
        'database/migrations/2026_07_08_000004_create_identity_biometric_artifacts_table.php',
    ];

    /** @return list<string> */
    private function forbiddenPhaseSixNames(): array
    {
        return [
            'Market'.'place',
            'Venue'.'Listing',
            'Venue'.'Asset',
            'Rental',
            'Hardware',
        ];
    }

    public function handle(): int
    {
        $failures = [];
        foreach (File::glob(base_path('Dockerfile*')) as $path) {
            $failures[] = $path;
        }
        foreach (['docker-compose.yml', 'compose.yml', 'docker-compose.yaml', 'compose.yaml'] as $name) {
            if (File::exists(base_path($name))) {
                $failures[] = base_path($name);
            }
        }

        foreach (File::allFiles(base_path()) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            if ($this->shouldSkipRepositoryFile($relative)) {
                continue;
            }

            if (! $this->isAllowedProductPath($relative)
                && preg_match('/\/Modules\/(Wallet|Scan|CheckIn)\//i', '/'.$relative)) {
                $failures[] = $relative;
            }

            if (preg_match(
                '/\/(IdentityVerification|IdentityAssurance|Marketplace|VenueAssets?|Rentals?|Hardware)(\/|\.|$)/i',
                '/'.$relative,
            ) === 1 && ! $this->isAllowedProductPath($relative)) {
                $failures[] = $relative;
            }

            if ($this->isAllowedProductPath($relative)) {
                continue;
            }

            if (! File::exists($file->getPathname())) {
                continue;
            }

            $contents = File::get($file->getPathname());
            foreach ($this->forbiddenPhaseSixNames() as $name) {
                if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $relative) === 1
                    || preg_match('/\b'.preg_quote($name, '/').'\b/i', $contents) === 1) {
                    $failures[] = "{$relative} contains {$name}";
                }
            }
        }

        foreach ($failures as $failure) {
            $this->components->error("Phase boundary violation: {$failure}");
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function isAllowedProductPath(string $relative): bool
    {
        foreach ($this->allowedProductPaths as $allowed) {
            if (str_starts_with($relative, str_replace('\\', '/', $allowed))) {
                return true;
            }
        }

        return false;
    }

    private function shouldSkipRepositoryFile(string $relative): bool
    {
        if (preg_match('#(^|/)(\.git|\.cursor|node_modules|vendor|storage|bootstrap/cache|mcps|agent-transcripts)(/|$)#', $relative) === 1) {
            return true;
        }

        if (str_starts_with($relative, 'specs/')
            || str_starts_with($relative, 'docs/')
            || in_array($relative, ['all_plan.md', 'Zonetec_PRD.md', 'front_plan_step_2.md'], true)) {
            return true;
        }

        if (in_array($relative, [
            'app/Console/Commands/CheckPhaseBoundary.php',
            'resources/js/__tests__/foundation-accessibility.test.tsx',
            'tests/Architecture/ModuleBoundaryTest.php',
            'tests/Architecture/Phase1ModuleBoundaryTest.php',
            'tests/Architecture/PhaseBoundaryTest.php',
            'tests/Architecture/Phase2ModuleBoundaryTest.php',
            'tests/Architecture/Phase3ModuleBoundaryTest.php',
            'tests/Architecture/Phase4ModuleBoundaryTest.php',
        ], true)) {
            return true;
        }

        return ! in_array(pathinfo($relative, PATHINFO_EXTENSION), [
            'php',
            'ts',
            'tsx',
            'js',
            'jsx',
            'json',
            'md',
            'yaml',
            'yml',
            'xml',
        ], true);
    }
}
