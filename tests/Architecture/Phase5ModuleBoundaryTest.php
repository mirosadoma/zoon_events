<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-5')]
#[Group('phase-5-isolation')]
final class Phase5ModuleBoundaryTest extends TestCase
{
    public function test_identity_verification_module_infrastructure_is_not_imported_outside_module(): void
    {
        $violations = [];
        $root = app_path('Modules');

        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $skipModules = ['AdminConsole'];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/app/Modules/IdentityVerification/')) {
                continue;
            }
            foreach ($skipModules as $skipModule) {
                if (str_contains($path, "/app/Modules/{$skipModule}/")) {
                    continue 2;
                }
            }
            $contents = file_get_contents($file->getPathname()) ?: '';
            if (preg_match('/use App\\\\Modules\\\\IdentityVerification\\\\Infrastructure\\\\/', $contents)) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations);
    }

    public function test_identity_verification_application_and_domain_layers_do_not_import_transport_clients(): void
    {
        $forbidden = ['GuzzleHttp', 'Http::', 'fsockopen', 'MqttClient'];
        $roots = [
            app_path('Modules/IdentityVerification/Application'),
            app_path('Modules/IdentityVerification/Domain'),
        ];
        $violations = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname()) ?: '';
                foreach ($forbidden as $needle) {
                    if (str_contains($contents, $needle)) {
                        $violations[] = "{$file->getPathname()} contains {$needle}";
                    }
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_phase_six_plus_product_names_are_absent_from_application_code(): void
    {
        // Phase 6 (Venue Marketplace) is in active implementation. This gate now
        // only forbids Phase 7/8 product vocabulary that must remain absent.
        $forbidden = ['VenueListing', 'HardwareFederation', 'CrossDeploymentCatalog'];
        $skipPaths = [
            'tests'.DIRECTORY_SEPARATOR,
            '__tests__'.DIRECTORY_SEPARATOR,
            'CheckPhaseBoundary.php',
            'VenueMarketplace'.DIRECTORY_SEPARATOR,
            'ViewModels'.DIRECTORY_SEPARATOR.'Marketplace'.DIRECTORY_SEPARATOR,
            'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'marketplace'.DIRECTORY_SEPARATOR,
            'pages'.DIRECTORY_SEPARATOR.'platform'.DIRECTORY_SEPARATOR.'marketplace'.DIRECTORY_SEPARATOR,
            'pages'.DIRECTORY_SEPARATOR.'tenant'.DIRECTORY_SEPARATOR.'venues'.DIRECTORY_SEPARATOR,
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
