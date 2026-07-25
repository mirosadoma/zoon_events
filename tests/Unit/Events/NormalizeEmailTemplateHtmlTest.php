<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\NormalizeEmailTemplateHtml;
use App\Modules\Events\Mail\CustomEventEmailMail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NormalizeEmailTemplateHtmlTest extends TestCase
{
    #[Test]
    public function it_wraps_inline_images_so_following_text_does_not_sit_beside_them(): void
    {
        $html = '<p>Hello<br><img src="https://example.test/a.jpg" alt="">To register</p>';

        $result = app(NormalizeEmailTemplateHtml::class)->execute($html);

        self::assertStringContainsString('email-image-block', $result);
        self::assertStringContainsString('display:block', $result);
        self::assertDoesNotMatchRegularExpression('/<img[^>]*>To register/', $result);
        self::assertStringContainsString('To register', $result);

        $imagePos = stripos($result, '<img');
        $textPos = stripos($result, 'To register');
        self::assertNotFalse($imagePos);
        self::assertNotFalse($textPos);
        self::assertLessThan($textPos, $imagePos);
    }

    #[Test]
    public function it_keeps_existing_image_rows_intact(): void
    {
        $html = <<<'HTML'
<table class="email-image-row" role="presentation"><tr>
<td><img class="email-template-image" src="https://example.test/a.jpg" alt="" width="180" height="90" style="width:180px;height:90px;"></td>
<td><img class="email-template-image" src="https://example.test/b.jpg" alt="" width="220" height="110" style="width:220px;height:110px;"></td>
</tr></table>
HTML;

        $result = app(NormalizeEmailTemplateHtml::class)->execute($html);

        self::assertStringContainsString('email-image-row', $result);
        self::assertSame(2, substr_count(strtolower($result), '<img'));
        self::assertStringNotContainsString('email-image-block', $result);
        self::assertMatchesRegularExpression('/width="180"[^>]*height="90"|height="90"[^>]*width="180"/', $result);
        self::assertMatchesRegularExpression('/width="220"[^>]*height="110"|height="110"[^>]*width="220"/', $result);
        self::assertStringContainsString('width:180px', $result);
        self::assertStringContainsString('width:220px', $result);
    }

    #[Test]
    public function it_preserves_side_by_side_layout_and_sizes_through_mail_embedding(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tenants/1/events/4/email-templates/a.jpg', $this->onePixelPng());
        Storage::disk('public')->put('tenants/1/events/4/email-templates/b.jpg', $this->onePixelPng());

        $html = <<<'HTML'
<p>Before</p>
<table class="email-image-row" role="presentation"><tr>
<td width="160"><img class="email-template-image" src="http://zoon.test/storage/tenants/1/events/4/email-templates/a.jpg" alt="" width="160" height="80" style="width:160px;height:80px;display:block;"></td>
<td width="200"><img class="email-template-image" src="http://zoon.test/storage/tenants/1/events/4/email-templates/b.jpg" alt="" width="200" height="100" style="width:200px;height:100px;display:block;"></td>
</tr></table>
<p>After</p>
HTML;

        $normalized = app(NormalizeEmailTemplateHtml::class)->execute($html);
        $mailable = new CustomEventEmailMail('Subject', $normalized, 'en');

        self::assertStringContainsString('email-image-row', $mailable->htmlBody);
        self::assertCount(2, $mailable->inlineImages);
        self::assertStringContainsString('width="160"', $mailable->htmlBody);
        self::assertStringContainsString('height="80"', $mailable->htmlBody);
        self::assertStringContainsString('width="200"', $mailable->htmlBody);
        self::assertStringContainsString('height="100"', $mailable->htmlBody);

        $beforePos = stripos($mailable->htmlBody, 'Before');
        $rowPos = stripos($mailable->htmlBody, 'email-image-row');
        $afterPos = stripos($mailable->htmlBody, 'After');
        self::assertNotFalse($beforePos);
        self::assertNotFalse($rowPos);
        self::assertNotFalse($afterPos);
        self::assertTrue($beforePos < $rowPos && $rowPos < $afterPos);

        $rendered = $mailable->render();
        self::assertStringContainsString('email-image-row', $rendered);
        self::assertStringContainsString('width="160"', $rendered);
        self::assertStringContainsString('width="200"', $rendered);
        self::assertStringNotContainsString('zoon.test/storage', $rendered);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
