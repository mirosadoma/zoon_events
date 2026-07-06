<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckPhaseBoundary extends Command
{
    protected $signature = 'zonetec:phase-boundary:check';

    protected $description = 'Reject container files and product implementation beyond the active Phase 2 scope.';

    /** @var list<string> */
    private array $allowedPhaseTwoPaths = [
        'app/Modules/WalletPasses',
        'app/Modules/Scanning',
        'resources/js/pages/tenant/checkin',
        'resources/js/components/wallet',
        'resources/js/components/checkin',
        'resources/js/types/phase2.ts',
        'tests/Feature/WalletPasses',
        'tests/Feature/Scanning',
        'tests/Contract/Phase2',
        'tests/Contract/Wallet',
        'tests/Browser/Phase2',
        'tests/Architecture/Phase2ModuleBoundaryTest.php',
        'tests/Feature/Authorization/Phase2PermissionMatrixTest.php',
        'tests/Support/Phase2MySqlTestCase.php',
        'lang/en/phase2.php',
        'lang/ar/phase2.php',
        'config/wallet.php',
    ];

    /** @return list<string> */
    private function forbiddenPhaseThreeNames(): array
    {
        return [
            'Kio'.'sk',
            'Badge'.'Print',
            'Manual'.'Desk',
            'Anti'.'Passback',
            'Acs'.'Lane',
            'Identity'.'Verification',
            'Market'.'place',
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

            if (! $this->isAllowedPhaseTwoPath($relative)
                && preg_match('/\/Modules\/(Wallet|Scan|CheckIn)\//i', '/'.$relative)) {
                $failures[] = $relative;
            }

            if (preg_match(
                '/\/(Kiosks?|Badges?|BadgePrint|ManualDesk|AntiPassback|AcsLanes?|ACS|IdentityVerification|Marketplace|Hardware|VenueAssets?|Rentals?)(\/|\.|$)/i',
                '/'.$relative,
            ) === 1 && ! $this->isAllowedPhaseTwoPath($relative)) {
                $failures[] = $relative;
            }

            $contents = File::get($file->getPathname());
            foreach ($this->forbiddenPhaseThreeNames() as $name) {
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

    private function isAllowedPhaseTwoPath(string $relative): bool
    {
        foreach ($this->allowedPhaseTwoPaths as $allowed) {
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
            || in_array($relative, ['all_plan.md', 'Zonetec_PRD.md'], true)) {
            return true;
        }

        if (in_array($relative, [
            'app/Console/Commands/CheckPhaseBoundary.php',
            'resources/js/__tests__/foundation-accessibility.test.tsx',
            'tests/Architecture/ModuleBoundaryTest.php',
            'tests/Architecture/Phase1ModuleBoundaryTest.php',
            'tests/Architecture/PhaseBoundaryTest.php',
            'tests/Architecture/Phase2ModuleBoundaryTest.php',
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
