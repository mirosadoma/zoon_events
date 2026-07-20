<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;

final readonly class RenderBadgeEmailHtmlAction
{
    /** @var array<string, array{w:int,h:int}> */
    private const PAPER_PRESETS = [
        'CR80' => ['w' => 242, 'h' => 153],
        'A6' => ['w' => 298, 'h' => 420],
        '4x3' => ['w' => 288, 'h' => 216],
        '4x6' => ['w' => 288, 'h' => 432],
    ];

    /** @var list<string> */
    private const GUIDE_FIELDS = ['job_title', 'custom_text', 'company'];

    /** @var array<string, string> */
    private const GUIDE_COLORS = [
        'job_title' => '#f59e0b',
        'custom_text' => '#10b981',
        'company' => '#8b5cf6',
    ];

    /** @var array<string, string> */
    private const GUIDE_LABELS = [
        'job_title' => 'Job title',
        'custom_text' => 'Custom text',
        'company' => 'Company',
    ];

    /**
     * @param  array<string, string|null>  $fieldValues
     */
    public function execute(
        BadgeTemplate $template,
        array $fieldValues,
        ?string $qrDataUri = null,
        bool $showFieldGuides = false,
    ): string {
        [$width, $height] = $this->canvasSize($template);
        $background = $this->safeColor($template->background_color) ?? '#ffffff';
        $items = $this->layoutItems((array) $template->layout);

        $parts = [];
        $parts[] = sprintf(
            '<div style="position:relative;width:%dpx;height:%dpx;background:%s;overflow:%s;margin:0 auto;border:1px solid #cbd5e1;-webkit-print-color-adjust:exact;print-color-adjust:exact;">',
            $width,
            $height,
            e($background),
            $showFieldGuides ? 'visible' : 'hidden',
        );

        $backgroundImageHtml = $this->backgroundImageHtml($template, $width, $height);
        if ($backgroundImageHtml !== '') {
            $parts[] = $backgroundImageHtml;
        }

        foreach ($items as $item) {
            $parts[] = $this->renderItem($item, $fieldValues, $qrDataUri, $showFieldGuides);
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function canvasSize(BadgeTemplate $template): array
    {
        // Designer already saves oriented canvas_width/height — use them as-is.
        if ($template->canvas_width && $template->canvas_height) {
            return [max(1, (int) $template->canvas_width), max(1, (int) $template->canvas_height)];
        }

        $orientation = ($template->orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $paperKey = is_string($template->paper_size) ? strtoupper($template->paper_size) : 'A6';
        if ($paperKey === '4X3') {
            $paperKey = '4x3';
        }
        if ($paperKey === '4X6') {
            $paperKey = '4x6';
        }
        $preset = self::PAPER_PRESETS[$paperKey] ?? self::PAPER_PRESETS[$template->paper_size] ?? self::PAPER_PRESETS['A6'];
        $baseW = $preset['w'];
        $baseH = $preset['h'];

        $width = $orientation === 'landscape' ? max($baseW, $baseH) : min($baseW, $baseH);
        $height = $orientation === 'landscape' ? min($baseW, $baseH) : max($baseW, $baseH);

        return [max(1, $width), max(1, $height)];
    }

    /**
     * @param  array<int|string, mixed>  $layout
     * @return list<array<string, mixed>>
     */
    private function layoutItems(array $layout): array
    {
        if ($layout === []) {
            return [];
        }

        if (array_is_list($layout)) {
            $items = [];
            foreach ($layout as $item) {
                if (is_array($item) && is_string($item['field'] ?? null)) {
                    $items[] = $item;
                }
            }

            return $items;
        }

        $items = [];
        foreach ($layout as $field => $meta) {
            $item = is_array($meta) ? $meta : [];
            $item['field'] = (string) $field;
            $item['x'] ??= 20;
            $item['y'] ??= 20 + (count($items) * 36);
            $item['width'] ??= 160;
            $item['height'] ??= 28;
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string|null>  $fieldValues
     */
    private function renderItem(array $item, array $fieldValues, ?string $qrDataUri, bool $showFieldGuides = false): string
    {
        $field = (string) ($item['field'] ?? '');
        $x = (int) ($item['x'] ?? 0);
        $y = (int) ($item['y'] ?? 0);
        $w = max(1, (int) ($item['width'] ?? 80));
        $h = max(1, (int) ($item['height'] ?? 28));
        $rotation = (int) ($item['rotation'] ?? 0);
        $radius = max(0, (int) ($item['borderRadius'] ?? 0));
        $bg = $this->safeColor(isset($item['backgroundColor']) ? (string) $item['backgroundColor'] : null);
        $fontSize = max(8, (int) ($item['fontSize'] ?? 12));
        $fontFamily = $this->safeFont(isset($item['fontFamily']) ? (string) $item['fontFamily'] : 'Arial');
        $fontWeight = ($item['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
        $color = $this->safeColor(isset($item['color']) ? (string) $item['color'] : null) ?? '#000000';
        $alignRaw = $item['textAlign'] ?? 'left';
        $align = in_array($alignRaw, ['left', 'center', 'right'], true) ? (string) $alignRaw : 'left';
        $isGuided = $showFieldGuides && in_array($field, self::GUIDE_FIELDS, true);
        $guideColor = self::GUIDE_COLORS[$field] ?? '#0ea5e9';

        $boxStyle = sprintf(
            'position:absolute;left:%dpx;top:%dpx;width:%dpx;height:%dpx;overflow:visible;box-sizing:border-box;z-index:1;%s%s',
            $x,
            $y,
            $w,
            $h,
            $bg !== null ? 'background:'.e($bg).';' : '',
            $radius > 0 ? 'border-radius:'.$radius.'px;' : '',
        );

        if ($isGuided) {
            $boxStyle .= sprintf(
                'outline:2px dashed %s;outline-offset:1px;background:%s;',
                e($guideColor),
                $bg !== null ? e($bg) : 'rgba(255,255,255,0.35)',
            );
        }

        if ($rotation !== 0) {
            $boxStyle .= sprintf('transform:rotate(%ddeg);transform-origin:center center;', $rotation);
        }

        if ($field === 'qr') {
            if ($qrDataUri === null || $qrDataUri === '') {
                return '';
            }

            return sprintf(
                '<div style="%s"><img src="%s" alt="QR" width="%d" height="%d" style="display:block;width:100%%;height:100%%;object-fit:contain;border:0;"></div>',
                $boxStyle,
                e($qrDataUri),
                $w,
                $h,
            );
        }

        if ($field === 'color_code') {
            $swatch = $this->safeColor($fieldValues['color_code'] ?? null)
                ?? $bg
                ?? '#3b82f6';

            return sprintf(
                '<div style="%sbackground:%s;"></div>',
                $boxStyle,
                e($swatch),
            );
        }

        if ($field === 'organizer_logo_ref' || $field === 'sponsor_logo_ref') {
            $src = $fieldValues[$field] ?? null;
            if (! is_string($src) || $src === '') {
                return '';
            }

            return sprintf(
                '<div style="%s"><img src="%s" alt="" width="%d" height="%d" style="display:block;width:100%%;height:100%%;object-fit:contain;border:0;"></div>',
                $boxStyle,
                e($src),
                $w,
                $h,
            );
        }

        $text = $field === 'custom_text'
            ? $this->resolveCustomText($item, $fieldValues)
            : (is_string($fieldValues[$field] ?? null) ? trim((string) $fieldValues[$field]) : '');

        if ($text === '' && ! $isGuided) {
            return '';
        }

        $displayText = $text !== ''
            ? $text
            : ('['.(self::GUIDE_LABELS[$field] ?? $field).']');
        $displayColor = $text !== '' ? $color : '#64748b';

        $textStyle = sprintf(
            'font-size:%dpx;font-family:%s,sans-serif;font-weight:%s;color:%s;text-align:%s;line-height:1.2;width:100%%;height:100%%;overflow:hidden;word-break:break-word;%s',
            $fontSize,
            e($fontFamily),
            $fontWeight,
            e($displayColor),
            $align,
            $text === '' ? 'font-style:italic;opacity:0.85;' : '',
        );

        $labelHtml = '';
        if ($isGuided) {
            $labelHtml = sprintf(
                '<span style="position:absolute;left:0;top:-14px;z-index:2;padding:1px 6px;border-radius:999px;background:%s;color:#fff;font:600 9px/1.2 Arial,sans-serif;white-space:nowrap;pointer-events:none;">%s</span>',
                e($guideColor),
                e(self::GUIDE_LABELS[$field] ?? $field),
            );
        }

        return sprintf(
            '<div style="%s">%s<div style="%s">%s</div></div>',
            $boxStyle,
            $labelHtml,
            $textStyle,
            e($displayText),
        );
    }

    private function safeColor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1 ? $value : null;
    }

    private function safeFont(string $font): string
    {
        $allowed = ['Inter', 'Arial', 'Cairo', 'Tajawal', 'monospace', 'Helvetica', 'sans-serif'];

        return in_array($font, $allowed, true) ? $font : 'Arial';
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string|null>  $fieldValues
     */
    private function resolveCustomText(array $item, array $fieldValues): string
    {
        if (is_string($fieldValues['custom_text'] ?? null) && trim((string) $fieldValues['custom_text']) !== '') {
            return trim((string) $fieldValues['custom_text']);
        }

        return is_string($item['text'] ?? null) ? trim((string) $item['text']) : '';
    }

    private function backgroundImageHtml(BadgeTemplate $template, int $width, int $height): string
    {
        $src = $this->backgroundImageSrc($template);
        if ($src === null) {
            return '';
        }

        return sprintf(
            '<img src="%s" alt="" width="%d" height="%d" style="position:absolute;inset:0;width:100%%;height:100%%;object-fit:cover;z-index:0;pointer-events:none;-webkit-print-color-adjust:exact;print-color-adjust:exact;">',
            e($src),
            $width,
            $height,
        );
    }

    private function backgroundImageSrc(BadgeTemplate $template): ?string
    {
        $path = $template->background_image_path;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'cid:')) {
            return $path;
        }

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = url($url);
        }

        return $url;
    }
}
