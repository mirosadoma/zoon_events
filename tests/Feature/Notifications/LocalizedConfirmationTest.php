<?php

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Application\Rendering\ConfirmationRenderer;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('notifications')]
#[Group('localized-confirmation')]
final class LocalizedConfirmationTest extends TestCase
{
    public function test_english_and_arabic_templates_are_bidi_safe_and_contain_only_safe_links(): void
    {
        $renderer = app(ConfirmationRenderer::class);
        foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
            $rendered = $renderer->render($locale, [
                'event_name' => $locale === 'ar' ? 'فعالية' : 'Event',
                'order_reference' => 'ORD-SAFE',
                'credential_url' => 'https://example.test/public/orders/ORD-SAFE',
            ]);
            self::assertStringContainsString('dir="'.$direction.'"', $rendered['body']);
            self::assertStringContainsString('ORD-SAFE', $rendered['body']);
            self::assertStringNotContainsString('token=', $rendered['body']);
            self::assertStringNotContainsString('secret', strtolower($rendered['body']));
        }
    }
}
