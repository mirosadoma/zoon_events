<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuleBoundaryTest extends TestCase
{
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

            if (preg_match('/\b(registration|ticketing|wallet|kiosk|acs|identity verification|marketplace)\b/i', $contents)) {
                $violations[] = $file.':forbidden-product-scope-name';
            }
        }

        return $violations;
    }
}
