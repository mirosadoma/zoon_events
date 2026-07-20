<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Support\BadgeLayoutFontSize;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
final class BadgeLayoutFontSizeTest extends TestCase
{
    private BadgeLayoutFontSize $fontSizes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fontSizes = new BadgeLayoutFontSize;
    }

    public function test_resolves_css_px_from_font_size_percent(): void
    {
        $item = ['fontSizePercent' => 10.0];

        self::assertSame(21, $this->fontSizes->resolveCssPx($item, 206));
    }

    public function test_derives_percent_from_legacy_font_size_px(): void
    {
        $item = ['fontSize' => 16];

        self::assertSame(7.8, round($this->fontSizes->resolvePercent($item, 206), 1));
        self::assertSame(16, $this->fontSizes->resolveCssPx($item, 206));
    }

    public function test_gd_points_scale_from_css_px(): void
    {
        $item = ['fontSizePercent' => 10.0];

        self::assertSame(16, $this->fontSizes->resolveGdPoints($item, 206));
    }
}
