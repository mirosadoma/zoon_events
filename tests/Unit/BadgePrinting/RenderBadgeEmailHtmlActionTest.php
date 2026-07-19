<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Actions\RenderBadgeEmailHtmlAction;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class RenderBadgeEmailHtmlActionTest extends TestCase
{
    public function test_renders_designer_layout_with_attendee_values_and_qr(): void
    {
        $template = new BadgeTemplate([
            'name' => 'Guest Badge',
            'paper_size' => 'A6',
            'printer_type' => 'thermal',
            'orientation' => 'portrait',
            'background_color' => '#f8fafc',
            'canvas_width' => 298,
            'canvas_height' => 420,
            'layout' => [
                [
                    'id' => 'f1',
                    'field' => 'attendee_name',
                    'x' => 24,
                    'y' => 40,
                    'width' => 250,
                    'height' => 32,
                    'fontSize' => 18,
                    'fontWeight' => 'bold',
                    'color' => '#0f172a',
                    'textAlign' => 'center',
                ],
                [
                    'id' => 'f2',
                    'field' => 'company',
                    'x' => 24,
                    'y' => 80,
                    'width' => 250,
                    'height' => 24,
                    'fontSize' => 12,
                    'color' => '#334155',
                    'textAlign' => 'center',
                ],
                [
                    'id' => 'f3',
                    'field' => 'qr',
                    'x' => 99,
                    'y' => 160,
                    'width' => 100,
                    'height' => 100,
                ],
                [
                    'id' => 'f4',
                    'field' => 'custom_text',
                    'x' => 24,
                    'y' => 280,
                    'width' => 250,
                    'height' => 20,
                    'text' => 'Visitor Pass',
                    'fontSize' => 11,
                    'color' => '#64748b',
                    'textAlign' => 'center',
                ],
                [
                    'id' => 'f5',
                    'field' => 'color_code',
                    'x' => 24,
                    'y' => 400,
                    'width' => 250,
                    'height' => 12,
                ],
            ],
        ]);

        $html = app(RenderBadgeEmailHtmlAction::class)->execute(
            $template,
            [
                'attendee_name' => 'Sara Ahmed',
                'company' => 'ZoneTec',
                'qr' => 'token-value',
                'color_code' => '#22c55e',
            ],
            'data:image/png;base64,abc123',
        );

        self::assertStringContainsString('width:298px', $html);
        self::assertStringContainsString('height:420px', $html);
        self::assertStringContainsString('background:#f8fafc', $html);
        self::assertStringContainsString('Sara Ahmed', $html);
        self::assertStringContainsString('ZoneTec', $html);
        self::assertStringContainsString('Visitor Pass', $html);
        self::assertStringContainsString('left:24px;top:40px', $html);
        self::assertStringContainsString('data:image/png;base64,abc123', $html);
        self::assertStringContainsString('background:#22c55e', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function test_custom_text_prefers_field_override_over_layout_default(): void
    {
        $template = new BadgeTemplate([
            'name' => 'Badge',
            'paper_size' => 'A6',
            'printer_type' => 'thermal',
            'orientation' => 'portrait',
            'canvas_width' => 298,
            'canvas_height' => 420,
            'layout' => [
                [
                    'id' => 'f1',
                    'field' => 'custom_text',
                    'x' => 10,
                    'y' => 10,
                    'width' => 100,
                    'height' => 20,
                    'text' => 'Template default',
                ],
            ],
        ]);

        $html = app(RenderBadgeEmailHtmlAction::class)->execute(
            $template,
            ['custom_text' => 'Print-time value'],
            null,
        );

        self::assertStringContainsString('Print-time value', $html);
        self::assertStringNotContainsString('Template default', $html);
    }

    public function test_uses_saved_canvas_dimensions_without_reorienting(): void
    {
        $template = new BadgeTemplate([
            'name' => 'Landscape Badge',
            'paper_size' => 'A6',
            'printer_type' => 'thermal',
            'orientation' => 'landscape',
            'canvas_width' => 420,
            'canvas_height' => 298,
            'layout' => [],
        ]);

        $html = app(RenderBadgeEmailHtmlAction::class)->execute($template, [], null);

        self::assertStringContainsString('width:420px', $html);
        self::assertStringContainsString('height:298px', $html);
    }

    public function test_escapes_attendee_text_and_skips_empty_fields(): void
    {
        $template = new BadgeTemplate([
            'name' => 'Badge',
            'paper_size' => 'CR80',
            'printer_type' => 'thermal',
            'orientation' => 'landscape',
            'layout' => [
                [
                    'id' => 'f1',
                    'field' => 'attendee_name',
                    'x' => 10,
                    'y' => 10,
                    'width' => 100,
                    'height' => 20,
                ],
                [
                    'id' => 'f2',
                    'field' => 'company',
                    'x' => 10,
                    'y' => 40,
                    'width' => 100,
                    'height' => 20,
                ],
            ],
        ]);

        $html = app(RenderBadgeEmailHtmlAction::class)->execute(
            $template,
            [
                'attendee_name' => '<b>Hack</b>',
                'company' => null,
            ],
            null,
        );

        self::assertStringContainsString('&lt;b&gt;Hack&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Hack</b>', $html);
        self::assertStringNotContainsString('left:10px;top:40px', $html);
    }
}
