<?php

namespace Tests\Feature\Smoke;

use Tests\TestCase;

class FoundationBootstrapTest extends TestCase
{
    public function test_root_endpoint_requires_authenticated_dashboard_session(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_localized_foundation_message_is_available_in_supported_locales(): void
    {
        $this->assertSame('Foundation ready', trans('foundation.status.ready', locale: 'en'));
        $this->assertSame('الأساس جاهز', trans('foundation.status.ready', locale: 'ar'));
    }
}
