<?php

namespace Tests\Architecture;

use Tests\TestCase;

class PhaseBoundaryTest extends TestCase
{
    public function test_foundation_contains_no_container_or_excluded_product_implementation(): void
    {
        $this->artisan('zonetec:phase-boundary:check')->assertSuccessful();
    }
}
