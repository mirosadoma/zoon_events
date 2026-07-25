<?php

namespace Tests\Unit\Events;

use App\Modules\Events\Application\Support\PrepareHtmlEmailEmbeddedImages;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PrepareHtmlEmailEmbeddedImagesTest extends TestCase
{
    #[Test]
    public function it_rewrites_storage_image_urls_to_cid_placeholders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'tenants/1/events/4/email-templates/banner.jpg',
            $this->onePixelPng(),
        );

        $html = '<p>Hello</p><img src="http://zoon.test/storage/tenants/1/events/4/email-templates/banner.jpg" alt="">';

        $result = app(PrepareHtmlEmailEmbeddedImages::class)->execute($html);

        self::assertStringContainsString('src="cid:email-img-1"', $result['html']);
        self::assertStringNotContainsString('zoon.test', $result['html']);
        self::assertCount(1, $result['images']);
        self::assertSame('email-img-1', $result['images'][0]['cid']);
        self::assertNotSame('', $result['images'][0]['bytes']);
    }

    #[Test]
    public function it_leaves_html_without_images_unchanged(): void
    {
        $html = '<p>No images here</p>';

        $result = app(PrepareHtmlEmailEmbeddedImages::class)->execute($html);

        self::assertSame($html, $result['html']);
        self::assertSame([], $result['images']);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
