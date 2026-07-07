<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-2')]
final class Phase2ModuleBoundaryTest extends TestCase
{
    public function test_wallet_and_scanning_modules_do_not_import_another_modules_infrastructure(): void
    {
        $roots = ['WalletPasses', 'Scanning'];
        $skipModules = ['Kiosk', 'BadgePrinting', 'AccessControl', 'Attendees'];
        $violations = [];

        foreach ($roots as $owner) {
            $root = app_path('Modules');
            if (! is_dir($root)) {
                $violations[] = "Missing module directory: {$root}";

                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (str_contains($path, "/app/Modules/{$owner}/")) {
                    continue;
                }
                foreach ($skipModules as $skipModule) {
                    if (str_contains($path, "/app/Modules/{$skipModule}/")) {
                        continue 2;
                    }
                }

                $contents = file_get_contents($file->getPathname()) ?: '';
                if (preg_match('/use App\\\\Modules\\\\'.$owner.'\\\\Infrastructure\\\\/', $contents)) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_phase_three_plus_product_names_are_absent_from_application_code(): void
    {
        $forbidden = ['Kiosk', 'BadgePrint', 'ManualDesk', 'AntiPassback', 'AcsLane', 'IdentityVerification', 'Marketplace'];
        $skipPaths = [
            'tests'.DIRECTORY_SEPARATOR,
            '__tests__'.DIRECTORY_SEPARATOR,
            'CheckPhaseBoundary.php',
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Kiosk'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'BadgePrinting'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'AccessControl'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Audit'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Listeners'.DIRECTORY_SEPARATOR.'Phase3'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Authorization'.DIRECTORY_SEPARATOR.'Policies'.DIRECTORY_SEPARATOR.'Phase3'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Authorization'.DIRECTORY_SEPARATOR.'Policies'.DIRECTORY_SEPARATOR.'Phase4'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Operations'.DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR.'Health'.DIRECTORY_SEPARATOR.'Checks'.DIRECTORY_SEPARATOR.'KioskFleetHealthCheck.php',
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'Scanning'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.'ModuleServiceProvider.php',
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'kiosk'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'manual-desk'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'badge-templates'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'acs'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'acs-health'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'gate-events'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'kiosk'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'manual-desk'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'acs'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'acs-health'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'gate-events'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'locales'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'types'.DIRECTORY_SEPARATOR.'phase3.ts',
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'types'.DIRECTORY_SEPARATOR.'phase4.ts',
            'routes'.DIRECTORY_SEPARATOR.'api.php',
            'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'2026_07_07_000002_add_acs_gate_scanner_type.php',
        ];
        $roots = [app_path(), resource_path('js'), base_path('routes'), database_path('migrations')];
        $violations = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php' && $file->getExtension() !== 'tsx' && $file->getExtension() !== 'ts') {
                    continue;
                }
                $path = $file->getPathname();
                foreach ($skipPaths as $skip) {
                    if (str_contains($path, $skip)) {
                        continue 2;
                    }
                }
                $contents = file_get_contents($path) ?: '';
                foreach ($forbidden as $name) {
                    if (preg_match('/\b'.preg_quote($name, '/').'\b/i', $contents) === 1) {
                        $violations[] = "{$path} contains {$name}";
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }
}
