<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Mail\CustomEventEmailMail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CustomEventEmailMailTest extends TestCase
{
    #[Test]
    public function it_embeds_storage_images_as_inline_attachments_in_rendered_html(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'tenants/1/events/4/email-templates/banner.jpg',
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                true,
            ),
        );

        $html = '<p>Invite</p><img src="http://zoon.test/storage/tenants/1/events/4/email-templates/banner.jpg" alt="banner">';

        $mailable = new CustomEventEmailMail('Subject', $html, 'en');

        self::assertStringContainsString('src="cid:email-img-1"', $mailable->htmlBody);
        self::assertCount(1, $mailable->inlineImages);

        $rendered = $mailable->render();

        self::assertStringContainsString('<p>Invite</p>', $rendered);
        self::assertStringContainsString('<img', $rendered);
        self::assertStringNotContainsString('zoon.test/storage', $rendered);
        self::assertMatchesRegularExpression('/src="(cid:[^"]+|data:image\/[^"]+)"/', $rendered);
    }
}
