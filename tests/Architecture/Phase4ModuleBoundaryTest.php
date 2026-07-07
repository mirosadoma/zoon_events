<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
final class Phase4ModuleBoundaryTest extends TestCase
{
    public function test_access_control_module_infrastructure_is_not_imported_outside_module(): void
    {
        $violations = [];
        $root = app_path('Modules');

        if (! is_dir($root)) {
            self::fail('Missing module directory: '.$root);
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (str_contains($path, '/app/Modules/AccessControl/')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname()) ?: '';
            if (preg_match('/use App\\\\Modules\\\\AccessControl\\\\Infrastructure\\\\/', $contents)) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations);
    }

    public function test_access_control_application_and_domain_layers_do_not_import_transport_clients(): void
    {
        $forbidden = ['GuzzleHttp', 'Http::', 'fsockopen', 'MqttClient'];
        $roots = [
            app_path('Modules/AccessControl/Application'),
            app_path('Modules/AccessControl/Domain'),
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

    public function test_phase_five_plus_product_names_are_absent_from_application_code(): void
    {
        $forbidden = ['IdentityVerification', 'IdentityAssurance', 'Marketplace', 'VenueListing'];
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

    public function test_only_authorize_and_ingest_actions_call_credential_validator_in_access_control(): void
    {
        $root = app_path('Modules/AccessControl');
        $allowedRelativePaths = [
            'Application/Actions/AuthorizeGateAction.php',
            'Application/Actions/IngestAccessEventAction.php',
        ];
        $violations = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(str_replace('\\', '/', $root)) + 1));
            $contents = file_get_contents($file->getPathname()) ?: '';

            if (! str_contains($contents, 'CredentialValidator')) {
                continue;
            }

            if (! str_contains($contents, '->validate(')) {
                continue;
            }

            if (! in_array($relative, $allowedRelativePaths, true)) {
                $violations[] = $relative;
            }
        }

        self::assertSame([], $violations, 'Unexpected CredentialValidator::validate call sites in AccessControl.');
    }
}
