<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Support\BadgeBackgroundRenderer;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
final class BadgeBackgroundRendererTest extends TestCase
{
    private BadgeBackgroundRenderer $backgrounds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backgrounds = new BadgeBackgroundRenderer;
    }

    public function test_builds_css_linear_gradient(): void
    {
        $css = $this->backgrounds->toCss('#ffffff', [
            'type' => 'linear',
            'angle' => 135,
            'stops' => [
                ['color' => '#2a2262', 'position' => 0],
                ['color' => '#1a1a54', 'position' => 100],
            ],
        ]);

        self::assertSame('linear-gradient(135.00deg, #2a2262 0.00%, #1a1a54 100.00%)', $css);
    }

    public function test_falls_back_to_solid_color_when_gradient_invalid(): void
    {
        self::assertSame('#2a2262', $this->backgrounds->toCss('#2a2262', null));
    }

    public function test_draws_gradient_pixels_on_canvas(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $canvas = imagecreatetruecolor(20, 10);
        self::assertNotFalse($canvas);

        $this->backgrounds->draw($canvas, 20, 10, '#ffffff', [
            'type' => 'linear',
            'angle' => 180,
            'stops' => [
                ['color' => '#ff0000', 'position' => 0],
                ['color' => '#0000ff', 'position' => 100],
            ],
        ]);

        $topPixel = imagecolorat($canvas, 10, 0);
        $bottomPixel = imagecolorat($canvas, 10, 9);

        self::assertSame(0xff, ($topPixel >> 16) & 0xFF);
        self::assertSame(0x00, ($topPixel >> 8) & 0xFF);
        self::assertSame(0x00, $topPixel & 0xFF);

        self::assertSame(0x00, ($bottomPixel >> 16) & 0xFF);
        self::assertSame(0x00, ($bottomPixel >> 8) & 0xFF);
        self::assertSame(0xff, $bottomPixel & 0xFF);

        imagedestroy($canvas);
    }
}
