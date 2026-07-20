<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Application\Support\BadgeBackgroundRenderer;
use App\Modules\BadgePrinting\Application\Support\BadgeLayoutFontSize;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Notifications\Application\Rendering\QrCodeImageDataUri;
use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final readonly class RenderBadgePngAction
{
    /** @var array<string, array{w:int,h:int}> */
    private const PAPER_PRESETS = [
        'CR80' => ['w' => 242, 'h' => 153],
        'A6' => ['w' => 298, 'h' => 420],
        '4x3' => ['w' => 288, 'h' => 216],
        '4x6' => ['w' => 288, 'h' => 432],
    ];

    public function __construct(
        private QrCodeImageDataUri $qrImages,
        private BadgeLayoutFontSize $fontSizes,
        private BadgeBackgroundRenderer $backgrounds,
    ) {}

    /**
     * @param  array<string, string|null>  $fieldValues
     */
    public function execute(BadgeTemplate $template, array $fieldValues, ?string $qrPayload = null): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        [$width, $height] = $this->canvasSize($template);
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            return null;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $gradient = is_array($template->background_gradient) ? $template->background_gradient : null;
        $this->backgrounds->draw($canvas, $width, $height, $template->background_color, $gradient);

        $this->drawBackgroundImage($canvas, $template, $width, $height);

        foreach ($this->layoutItems((array) $template->layout) as $item) {
            $this->drawItem($canvas, $item, $fieldValues, $qrPayload, $height);
        }

        ob_start();
        imagepng($canvas);
        imagedestroy($canvas);
        $png = ob_get_clean();

        return is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string|null>  $fieldValues
     */
    private function drawItem(
        GdImage $canvas,
        array $item,
        array $fieldValues,
        ?string $qrPayload,
        int $canvasHeight,
    ): void {
        $field = (string) ($item['field'] ?? '');
        $x = (int) ($item['x'] ?? 0);
        $y = (int) ($item['y'] ?? 0);
        $w = max(1, (int) ($item['width'] ?? 80));
        $h = max(1, (int) ($item['height'] ?? 28));
        $rotation = (int) ($item['rotation'] ?? 0);

        $layer = $this->createTransparentLayer($w, $h);
        if ($layer === null) {
            return;
        }

        $radius = max(0, min((int) ($item['borderRadius'] ?? 0), (int) floor(min($w, $h) / 2)));
        $fieldBackground = $this->parseFieldBackgroundColor(
            isset($item['backgroundColor']) ? (string) $item['backgroundColor'] : null,
        );

        if ($fieldBackground !== null) {
            $this->fillRoundedRect($layer, $w, $h, $radius, $fieldBackground);
        }

        imagealphablending($layer, true);

        if ($field === 'qr') {
            $this->drawQrOnLayer($layer, $w, $h, $qrPayload);
        } elseif ($field === 'color_code') {
            $swatch = $this->parseColor($fieldValues['color_code'] ?? null) ?? ['r' => 59, 'g' => 130, 'b' => 246];
            $this->fillRoundedRect($layer, $w, $h, $radius, $swatch);
        } elseif ($field === 'organizer_logo_ref' || $field === 'sponsor_logo_ref') {
            $src = $fieldValues[$field] ?? null;
            if (is_string($src) && $src !== '') {
                $this->drawContainedImageOnLayer($layer, $src, $w, $h);
            }
        } else {
            $text = $field === 'custom_text'
                ? $this->resolveCustomText($item, $fieldValues)
                : (is_string($fieldValues[$field] ?? null) ? trim((string) $fieldValues[$field]) : '');

            if ($text !== '') {
                $fontPoints = $this->fontSizes->resolveGdPoints($item, $canvasHeight);
                $this->drawTextOnLayer(
                    $layer,
                    $text,
                    $w,
                    $h,
                    $fontPoints,
                    ($item['fontWeight'] ?? 'normal') === 'bold',
                    $this->parseColor(isset($item['color']) ? (string) $item['color'] : null) ?? ['r' => 0, 'g' => 0, 'b' => 0],
                    $this->resolveHorizontalAlign($item['textAlign'] ?? null),
                    $this->resolveVerticalAlign($item['verticalAlign'] ?? null),
                );
            }
        }

        if ($rotation !== 0) {
            imagealphablending($layer, false);
            imagesavealpha($layer, true);
            $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
            $rotated = imagerotate($layer, -$rotation, $transparent);
            imagedestroy($layer);
            if ($rotated === false) {
                return;
            }
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
            $rw = imagesx($rotated);
            $rh = imagesy($rotated);
            $this->mergeLayerOntoCanvas(
                $canvas,
                $rotated,
                $x + (int) (($w - $rw) / 2),
                $y + (int) (($h - $rh) / 2),
            );
            imagedestroy($rotated);

            return;
        }

        $this->mergeLayerOntoCanvas($canvas, $layer, $x, $y);
        imagedestroy($layer);
    }

    private function createTransparentLayer(int $width, int $height): ?GdImage
    {
        $layer = imagecreatetruecolor($width, $height);
        if ($layer === false) {
            return null;
        }

        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        $transparent = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        imagefilledrectangle($layer, 0, 0, $width, $height, $transparent);

        return $layer;
    }

    /**
     * @param  array{r:int,g:int,b:int}  $rgb
     */
    private function fillRoundedRect(GdImage $layer, int $width, int $height, int $radius, array $rgb): void
    {
        imagealphablending($layer, false);
        $fill = imagecolorallocatealpha($layer, $rgb['r'], $rgb['g'], $rgb['b'], 0);

        if ($radius <= 0) {
            imagefilledrectangle($layer, 0, 0, $width - 1, $height - 1, $fill);

            return;
        }

        imagefilledrectangle($layer, $radius, 0, $width - $radius - 1, $height - 1, $fill);
        imagefilledrectangle($layer, 0, $radius, $width - 1, $height - $radius - 1, $fill);
        imagefilledellipse($layer, $radius, $radius, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($layer, $width - $radius - 1, $radius, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($layer, $radius, $height - $radius - 1, $radius * 2, $radius * 2, $fill);
        imagefilledellipse($layer, $width - $radius - 1, $height - $radius - 1, $radius * 2, $radius * 2, $fill);
    }

    private function mergeLayerOntoCanvas(GdImage $canvas, GdImage $layer, int $dstX, int $dstY): void
    {
        $width = imagesx($layer);
        $height = imagesy($layer);
        $canvasW = imagesx($canvas);
        $canvasH = imagesy($canvas);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $src = imagecolorat($layer, $x, $y);
                $srcAlpha = ($src >> 24) & 0x7F;
                if ($srcAlpha >= 127) {
                    continue;
                }

                $cx = $dstX + $x;
                $cy = $dstY + $y;
                if ($cx < 0 || $cy < 0 || $cx >= $canvasW || $cy >= $canvasH) {
                    continue;
                }

                $srcR = ($src >> 16) & 0xFF;
                $srcG = ($src >> 8) & 0xFF;
                $srcB = $src & 0xFF;

                if ($srcAlpha === 0) {
                    imagesetpixel($canvas, $cx, $cy, imagecolorallocate($canvas, $srcR, $srcG, $srcB));

                    continue;
                }

                $dst = imagecolorat($canvas, $cx, $cy);
                $dstR = ($dst >> 16) & 0xFF;
                $dstG = ($dst >> 8) & 0xFF;
                $dstB = $dst & 0xFF;
                $opacity = (127 - $srcAlpha) / 127;
                $r = (int) round(($srcR * $opacity) + ($dstR * (1 - $opacity)));
                $g = (int) round(($srcG * $opacity) + ($dstG * (1 - $opacity)));
                $b = (int) round(($srcB * $opacity) + ($dstB * (1 - $opacity)));
                imagesetpixel($canvas, $cx, $cy, imagecolorallocate($canvas, $r, $g, $b));
            }
        }
    }

    /** @return array{r:int,g:int,b:int}|null */
    private function parseFieldBackgroundColor(?string $value): ?array
    {
        if ($value === null || $value === '' || strcasecmp($value, 'transparent') === 0) {
            return null;
        }

        return $this->parseColor($value);
    }

    private function drawQrOnLayer(GdImage $layer, int $width, int $height, ?string $qrPayload): void
    {
        if ($qrPayload === null || $qrPayload === '') {
            return;
        }

        $png = $this->qrImages->pngBytesFromPayload($qrPayload, max($width, $height));
        if ($png === null) {
            return;
        }

        $image = imagecreatefromstring($png);
        if ($image === false) {
            return;
        }

        $this->copyContained($layer, $image, 0, 0, $width, $height);
        imagedestroy($image);
    }

    private function drawContainedImageOnLayer(GdImage $layer, string $src, int $width, int $height): void
    {
        $image = $this->loadImageResource($src);
        if ($image === null) {
            return;
        }

        $this->copyContained($layer, $image, 0, 0, $width, $height);
        imagedestroy($image);
    }

    private function copyContained(GdImage $dest, GdImage $src, int $dx, int $dy, int $dw, int $dh): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= 0 || $sh <= 0) {
            return;
        }

        $scale = min($dw / $sw, $dh / $sh);
        $tw = max(1, (int) round($sw * $scale));
        $th = max(1, (int) round($sh * $scale));
        $ox = $dx + (int) (($dw - $tw) / 2);
        $oy = $dy + (int) (($dh - $th) / 2);

        imagecopyresampled($dest, $src, $ox, $oy, 0, 0, $tw, $th, $sw, $sh);
    }

    /**
     * @param  array{r:int,g:int,b:int}  $color
     */
    private function drawTextOnLayer(
        GdImage $layer,
        string $text,
        int $width,
        int $height,
        int $fontSize,
        bool $bold,
        array $color,
        string $horizontal,
        string $vertical,
    ): void {
        $font = $this->fontPath($bold);
        $textColor = imagecolorallocate($layer, $color['r'], $color['g'], $color['b']);

        if ($font !== null && function_exists('imagettfbbox')) {
            $lines = $this->wrapText($text, $font, $fontSize, $width - 8);
            $lineHeight = (int) round($fontSize * 1.25);
            $blockHeight = count($lines) * $lineHeight;
            $startY = match ($vertical) {
                'top' => $fontSize + 2,
                'bottom' => max($fontSize + 2, $height - $blockHeight + $fontSize),
                default => max($fontSize + 2, (int) (($height - $blockHeight) / 2) + $fontSize),
            };

            foreach ($lines as $index => $line) {
                $box = imagettfbbox($fontSize, 0, $font, $line);
                if ($box === false) {
                    continue;
                }
                $lineWidth = abs($box[2] - $box[0]);
                $x = match ($horizontal) {
                    'right' => max(4, $width - $lineWidth - 4),
                    'center' => max(4, (int) (($width - $lineWidth) / 2)),
                    default => 4,
                };
                $y = $startY + ($index * $lineHeight);
                imagettftext($layer, $fontSize, 0, $x, $y, $textColor, $font, $line);
            }

            return;
        }

        $fallback = substr($text, 0, 64);
        $x = match ($horizontal) {
            'right' => max(0, $width - (strlen($fallback) * 6)),
            'center' => max(0, (int) (($width - (strlen($fallback) * 6)) / 2)),
            default => 2,
        };
        $y = match ($vertical) {
            'bottom' => max(0, $height - 14),
            'top' => 2,
            default => max(0, (int) (($height - 12) / 2)),
        };
        imagestring($layer, $bold ? 5 : 3, $x, $y, $fallback, $textColor);
    }

    /** @return list<string> */
    private function wrapText(string $text, string $font, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === []) {
            return [];
        }

        $lines = [];
        $current = array_shift($words) ?? '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $width = $box === false ? strlen($candidate) * ($fontSize / 2) : abs($box[2] - $box[0]);

            if ($width <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [trim($text)] : $lines;
    }

    private function drawBackgroundImage(GdImage $canvas, BadgeTemplate $template, int $width, int $height): void
    {
        $path = $template->background_image_path;
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        $image = $this->loadImageResource($path);
        if ($image === null) {
            return;
        }

        $sw = imagesx($image);
        $sh = imagesy($image);
        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($image);

            return;
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sw, $sh);
        imagedestroy($image);
    }

    private function loadImageResource(string $src): ?GdImage
    {
        try {
            if (str_starts_with($src, 'data:')) {
                if (! preg_match('#^data:([^;,]+)?(?:;base64)?,(.+)$#s', $src, $matches)) {
                    return null;
                }
                $payload = str_contains($src, ';base64,')
                    ? base64_decode($matches[2], true)
                    : rawurldecode($matches[2]);
                if ($payload === false || $payload === '') {
                    return null;
                }

                $image = imagecreatefromstring($payload);

                return $image === false ? null : $image;
            }

            if (preg_match('#^https?://#i', $src) === 1) {
                $response = Http::timeout(5)->get($src);
                if (! $response->successful()) {
                    return null;
                }
                $image = imagecreatefromstring($response->body());

                return $image === false ? null : $image;
            }

            $relative = ltrim($src, '/');
            if (str_starts_with($relative, 'storage/')) {
                $relative = substr($relative, strlen('storage/'));
            }
            $absolute = Storage::disk('public')->path($relative);
            if (! is_file($absolute)) {
                return null;
            }

            $image = imagecreatefromstring((string) file_get_contents($absolute));

            return $image === false ? null : $image;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:int,1:int} */
    private function canvasSize(BadgeTemplate $template): array
    {
        if ($template->canvas_width && $template->canvas_height) {
            return [max(1, (int) $template->canvas_width), max(1, (int) $template->canvas_height)];
        }

        $orientation = ($template->orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $paperKey = is_string($template->paper_size) ? strtoupper($template->paper_size) : 'A6';
        $preset = self::PAPER_PRESETS[$paperKey] ?? self::PAPER_PRESETS['A6'];
        $width = $orientation === 'landscape' ? max($preset['w'], $preset['h']) : min($preset['w'], $preset['h']);
        $height = $orientation === 'landscape' ? min($preset['w'], $preset['h']) : max($preset['w'], $preset['h']);

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
            $items[] = $item;
        }

        return $items;
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

    private function resolveHorizontalAlign(mixed $value): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? (string) $value : 'left';
    }

    private function resolveVerticalAlign(mixed $value): string
    {
        return in_array($value, ['top', 'center', 'bottom'], true) ? (string) $value : 'center';
    }

    /** @return array{r:int,g:int,b:int}|null */
    private function parseColor(?string $value): ?array
    {
        if ($value === null || ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
            return null;
        }

        $hex = $matches[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function fontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\ARIALBD.TTF',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ]
            : [
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\ARIAL.TTF',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
