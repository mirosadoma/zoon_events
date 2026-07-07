<?php

namespace Tests\Integration\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-4')]
#[Group('phase-4-isolation')]
final class Phase4ModuleBoundaryQueryTest extends TestCase
{
    public function test_no_class_outside_access_control_references_access_control_models(): void
    {
        $violations = [];
        $root = app_path('Modules');

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
            if (preg_match('/use App\\\\Modules\\\\AccessControl\\\\Infrastructure\\\\Persistence\\\\Models\\\\/', $contents)) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame([], $violations);
    }
}
