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
