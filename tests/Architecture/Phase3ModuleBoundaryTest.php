<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-3')]
#[Group('phase-3-isolation')]
final class Phase3ModuleBoundaryTest extends TestCase
{
    public function test_kiosk_and_badge_printing_modules_do_not_import_another_modules_infrastructure(): void
    {
        $roots = ['Kiosk', 'BadgePrinting'];
        $violations = [];

        foreach ($roots as $owner) {
            $root = app_path('Modules');
            if (! is_dir($root)) {
                $violations[] = "Missing module directory: {$root}";

                continue;
            }
            $skipModules = ['AdminConsole'];
            if ($owner === 'BadgePrinting') {
                $skipModules[] = 'Kiosk';
            }
            if ($owner === 'Kiosk') {
                $skipModules[] = 'BadgePrinting';
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

    public function test_phase_four_plus_product_names_are_absent_from_application_code(): void
    {
        $forbidden = ['AcsLane', 'AntiPassback', 'IdentityVerification', 'Marketplace', 'GateAuthorization'];
        $skipPaths = [
            'tests'.DIRECTORY_SEPARATOR,
            '__tests__'.DIRECTORY_SEPARATOR,
            'CheckPhaseBoundary.php',
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'AccessControl'.DIRECTORY_SEPARATOR,
            'app'.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.'AdminConsole'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'acs'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'acs-health'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'gate-events'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'acs'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'acs-health'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'gate-events'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'types'.DIRECTORY_SEPARATOR.'phase4.ts',
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'navigation'.DIRECTORY_SEPARATOR,
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'tenant-navigation.ts',
            'resources'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.'kiosk'.DIRECTORY_SEPARATOR,
            'routes'.DIRECTORY_SEPARATOR.'web.php',
        ];
        $roots = [app_path(), resource_path('js'), base_path('routes'), database_path('migrations')];
        $violations = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $ext = $file->getExtension();
                if (! in_array($ext, ['php', 'tsx', 'ts'], true)) {
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
