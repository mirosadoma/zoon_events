<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Support\PrepareBadgeEmailEmbeddedImages;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class PrepareBadgeEmailEmbeddedImagesTest extends TestCase
{
    public function test_replaces_logo_and_background_urls_with_cid_placeholders(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('badges/organizer.png', $this->onePixelPng());
        Storage::disk('public')->put('badges/background.png', $this->onePixelPng());

        $template = new BadgeTemplate([
            'name' => 'Guest Badge',
            'paper_size' => 'A6',
            'printer_type' => 'thermal',
            'background_image_path' => 'badges/background.png',
        ]);

        $result = app(PrepareBadgeEmailEmbeddedImages::class)->execute($template, [
            'organizer_logo_ref' => url('/storage/badges/organizer.png'),
            'sponsor_logo_ref' => null,
            'attendee_name' => 'Sara Ahmed',
        ]);

        self::assertSame('cid:badge-organizer-logo', $result['fields']['organizer_logo_ref']);
        self::assertSame('cid:badge-background', $result['template']->background_image_path);
        self::assertCount(2, $result['images']);
        self::assertSame('badge-organizer-logo', $result['images'][0]['cid']);
        self::assertSame('badge-background', $result['images'][1]['cid']);
        self::assertNotSame('', $result['images'][0]['bytes']);
    }

    private function onePixelPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
