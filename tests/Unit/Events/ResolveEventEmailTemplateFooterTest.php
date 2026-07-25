<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\ResolveEventEmailTemplate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResolveEventEmailTemplateFooterTest extends TestCase
{
    #[Test]
    public function it_appends_unsubscribe_footer_once(): void
    {
        $resolver = app(ResolveEventEmailTemplate::class);

        $html = $resolver->appendUnsubscribeFooter('<p>Hello</p>', 'en');

        self::assertStringContainsString('data-email-unsubscribe="1"', $html);
        self::assertStringContainsString('You can do unsubscribe by clicking', $html);
        self::assertStringContainsString('notifications/unsubscribe', $html);
        self::assertStringContainsString('>here</a>', $html);

        $again = $resolver->appendUnsubscribeFooter($html, 'en');
        self::assertSame(1, substr_count($again, 'data-email-unsubscribe="1"'));
    }

    #[Test]
    public function it_appends_arabic_unsubscribe_footer(): void
    {
        $html = app(ResolveEventEmailTemplate::class)->appendUnsubscribeFooter('<p>مرحبا</p>', 'ar');

        self::assertStringContainsString('يمكنك إلغاء الاشتراك', $html);
        self::assertStringContainsString('>هنا</a>', $html);
        self::assertStringContainsString('/ar/notifications/unsubscribe', $html);
    }
}
