<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuleBoundaryTest extends TestCase
{
    /** @var list<string> */
    private array $allowedProductScopePaths = [
        'app/Modules/WalletPasses',
        'app/Modules/Scanning',
        'app/Modules/Kiosk',
        'app/Modules/BadgePrinting',
        'app/Modules/AccessControl',
        'app/Modules/AdminConsole',
        'app/Console/Commands/CheckDocumentation.php',
        'app/Console/Commands/RefreshCheckInSummary.php',
        'app/Modules/Authorization/Policies/Phase2',
        'app/Modules/Authorization/Policies/Phase3',
        'app/Modules/Authorization/Policies/Phase4',
        'app/Modules/Audit/Application/Listeners/Phase3',
        'app/Modules/Audit/Application/Listeners/Phase4',
        'app/Modules/Operations/Application/Configuration/ConfigurationValidator.php',
        'app/Modules/Operations/Application/Health/Checks/AppleWalletHealthCheck.php',
        'app/Modules/Operations/Application/Health/Checks/GoogleWalletHealthCheck.php',
        'app/Modules/Operations/Application/Health/Checks/KioskFleetHealthCheck.php',
        'app/Modules/Operations/Application/Health/HealthService.php',
        'app/Modules/Shared/Http/Middleware/RequireIdempotencyKey.php',
        'app/Modules/Shared/Http/Problems/Phase3Problem.php',
        'app/Modules/Shared/Http/Problems/Phase4Problem.php',
        'app/Modules/Attendees/Application/Actions/RegisterWalkUpAttendeeAction.php',
        'app/Providers/AppServiceProvider.php',
        'app/Providers/ModuleServiceProvider.php',
        'config/wallet.php',
        'config/acs.php',
        'config/printing.php',
    ];

    #[Test]
    public function repository_code_avoids_cross_module_infrastructure_imports_and_product_scope_names(): void
    {
        $violations = $this->findViolations(base_path('app'));

        $this->assertSame([], $violations, 'Unexpected architecture violations: '.json_encode($violations, JSON_PRETTY_PRINT));
    }

    #[Test]
    public function deliberate_fixture_triggers_the_boundary_rule(): void
    {
        $violations = $this->findViolations(base_path('tests/Fixtures/Architecture'));

        $this->assertNotEmpty($violations);
    }

    /**
     * @return list<string>
     */
    private function findViolations(string $root): array
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $file = $fileInfo->getPathname();
            if (str_ends_with($file, DIRECTORY_SEPARATOR.'CheckPhaseBoundary.php')) {
                continue;
            }
            $contents = file_get_contents($file) ?: '';

            if (str_contains($contents, 'use App\\Modules\\Identity\\Infrastructure\\')
                && str_contains($file, DIRECTORY_SEPARATOR.'Tenancy'.DIRECTORY_SEPARATOR)) {
                $violations[] = $file.':cross-module-infrastructure-import';
            }

            if ($this->isAllowedProductScopePath($file) === false
                && preg_match('/\b(wallet|kiosk|scanner|check-in|badge|acs|identity verification|marketplace)\b/i', $contents)) {
                $violations[] = $file.':forbidden-product-scope-name';
            }
        }

        return $violations;
    }

    private function isAllowedProductScopePath(string $path): bool
    {
        $relative = str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));

        foreach ($this->allowedProductScopePaths as $allowed) {
            if (str_starts_with($relative, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
