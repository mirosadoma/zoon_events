<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Actions\SendCustomConfirmationEmail;
use App\Modules\Events\Mail\CustomEventEmailMail;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConfirmationEmailQrPlaceholderTest extends TestCase
{
    #[Test]
    public function it_embeds_qr_png_for_custom_confirmation_placeholder(): void
    {
        $qr = app(QrCodeImageDataUri::class)->pngBytesFromPayload('zt1.test-payload', 200);
        self::assertNotNull($qr);

        $html = '<p>Hello {{user_name}}</p><p>{{qr_code}}</p>';
        $rendered = strtr($html, [
            '{{user_name}}' => 'Amr Mohamed',
            '{{qr_code}}' => '<img src="cid:'.SendCustomConfirmationEmail::QR_CONTENT_ID.'" alt="QR Code" width="200" height="200" />',
        ]);

        $mailable = new CustomEventEmailMail(
            'Registration Success',
            $rendered,
            'en',
            [[
                'cid' => SendCustomConfirmationEmail::QR_CONTENT_ID,
                'bytes' => $qr,
                'mime' => 'image/png',
                'filename' => 'confirmation-qr.png',
            ]],
            $qr,
            'event-qr.png',
        );

        self::assertStringContainsString('cid:'.SendCustomConfirmationEmail::QR_CONTENT_ID, $mailable->htmlBody);
        self::assertCount(1, $mailable->inlineImages);
        self::assertSame(SendCustomConfirmationEmail::QR_CONTENT_ID, $mailable->inlineImages[0]['cid']);
        self::assertNotSame('', $mailable->attachmentPngBytes);

        $output = $mailable->render();
        self::assertStringContainsString('Amr Mohamed', $output);
        self::assertStringContainsString('<img', $output);
        self::assertStringNotContainsString('zt1.test-payload', $output);
        self::assertMatchesRegularExpression('/src="(cid:[^"]+|data:image\/[^"]+)"/', $output);
    }
}
