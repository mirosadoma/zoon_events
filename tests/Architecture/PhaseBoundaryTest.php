<?php

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhaseBoundaryTest extends TestCase
{
    public function test_foundation_contains_no_container_or_excluded_product_implementation(): void
    {
        $this->artisan('zonetec:phase-boundary:check')->assertSuccessful();
    }

    public function test_controlled_phase_two_product_fixtures_are_rejected(): void
    {
        foreach (['IdentityVerification', 'Marketplace', 'VenueListing', 'Hardware'] as $name) {
            $directory = app_path($name);
            File::ensureDirectoryExists($directory);
            $fixture = $directory.'/ForbiddenFixture.php';
            File::put($fixture, '<?php // controlled phase-boundary fixture');
            try {
                $this->artisan('zonetec:phase-boundary:check')->assertFailed();
            } finally {
                File::delete($fixture);
                File::deleteDirectory($directory);
            }
        }
    }
}
