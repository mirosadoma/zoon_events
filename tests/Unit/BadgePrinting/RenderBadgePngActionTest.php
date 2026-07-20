<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Actions\RenderBadgePngAction;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class RenderBadgePngActionTest extends TestCase
{
    public function test_renders_png_bytes_for_badge_layout(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $template = new BadgeTemplate([
            'name' => 'Guest Badge',
            'paper_size' => 'custom',
            'printer_type' => 'thermal',
            'background_color' => '#1a1a54',
            'canvas_width' => 420,
            'canvas_height' => 206,
            'layout' => [
                [
                    'field' => 'attendee_name',
                    'x' => 176,
                    'y' => 36,
                    'width' => 120,
                    'height' => 28,
                    'fontSize' => 16,
                    'fontWeight' => 'bold',
                    'color' => '#ffffff',
                    'textAlign' => 'center',
                    'verticalAlign' => 'center',
                    'backgroundColor' => '#2a2262',
                ],
                [
                    'field' => 'qr',
                    'x' => 28,
                    'y' => 108,
                    'width' => 80,
                    'height' => 80,
                ],
            ],
        ]);

        $png = app(RenderBadgePngAction::class)->execute(
            $template,
            ['attendee_name' => 'Amr Sadoma'],
            'sample-qr-payload',
        );

        self::assertIsString($png);
        self::assertNotSame('', $png);
        self::assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);

        // Sample a corner pixel inside the field box (away from centered text).
        $fieldPixel = imagecolorat($image, 178, 38);
        self::assertSame(0x2a, ($fieldPixel >> 16) & 0xFF);
        self::assertSame(0x22, ($fieldPixel >> 8) & 0xFF);
        self::assertSame(0x62, $fieldPixel & 0xFF);

        imagedestroy($image);
    }

    public function test_rounded_corners_do_not_leave_black_pixels(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $template = new BadgeTemplate([
            'name' => 'Rounded Badge',
            'paper_size' => 'custom',
            'printer_type' => 'thermal',
            'background_color' => '#2a2262',
            'canvas_width' => 200,
            'canvas_height' => 100,
            'layout' => [
                [
                    'field' => 'custom_text',
                    'text' => 'VIP',
                    'x' => 40,
                    'y' => 30,
                    'width' => 120,
                    'height' => 40,
                    'fontSize' => 14,
                    'color' => '#ffffff',
                    'textAlign' => 'center',
                    'verticalAlign' => 'center',
                    'backgroundColor' => '#333333',
                    'borderRadius' => 12,
                ],
            ],
        ]);

        $png = app(RenderBadgePngAction::class)->execute($template, [], null);
        self::assertIsString($png);

        $image = imagecreatefromstring($png);
        self::assertNotFalse($image);

        // Outside the rounded corner (top-left of the field box) should show badge bg, not black.
        $cornerPixel = imagecolorat($image, 40, 30);
        self::assertSame(0x2a, ($cornerPixel >> 16) & 0xFF);
        self::assertSame(0x22, ($cornerPixel >> 8) & 0xFF);
        self::assertSame(0x62, $cornerPixel & 0xFF);

        // Transparent field should also reveal badge background.
        $transparentTemplate = new BadgeTemplate([
            'name' => 'Transparent Field',
            'paper_size' => 'custom',
            'printer_type' => 'thermal',
            'background_color' => '#2a2262',
            'canvas_width' => 200,
            'canvas_height' => 100,
            'layout' => [
                [
                    'field' => 'custom_text',
                    'text' => 'OK',
                    'x' => 40,
                    'y' => 30,
                    'width' => 120,
                    'height' => 40,
                    'fontSize' => 14,
                    'color' => '#ffffff',
                    'textAlign' => 'center',
                    'verticalAlign' => 'center',
                ],
            ],
        ]);

        $transparentPng = app(RenderBadgePngAction::class)->execute($transparentTemplate, [], null);
        self::assertIsString($transparentPng);
        $transparentImage = imagecreatefromstring($transparentPng);
        self::assertNotFalse($transparentImage);

        $bgPixel = imagecolorat($transparentImage, 42, 32);
        self::assertSame(0x2a, ($bgPixel >> 16) & 0xFF);
        self::assertSame(0x22, ($bgPixel >> 8) & 0xFF);
        self::assertSame(0x62, $bgPixel & 0xFF);

        imagedestroy($image);
        imagedestroy($transparentImage);
    }
}
