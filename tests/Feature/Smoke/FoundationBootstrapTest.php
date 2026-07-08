<?php

namespace Tests\Feature\Smoke;

use Tests\TestCase;

class FoundationBootstrapTest extends TestCase
{
    public function test_public_home_is_available_and_dashboard_requires_authentication(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_localized_foundation_message_is_available_in_supported_locales(): void
    {
        $this->assertSame('Foundation ready', trans('foundation.status.ready', locale: 'en'));
        $this->assertSame('الأساس جاهز', trans('foundation.status.ready', locale: 'ar'));
    }
}
