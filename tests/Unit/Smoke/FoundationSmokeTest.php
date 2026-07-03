<?php

namespace Tests\Unit\Smoke;

use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    public function test_supported_locales_are_configured(): void
    {
        $this->assertSame(['en', 'ar'], config('app.supported_locales'));
    }
}
