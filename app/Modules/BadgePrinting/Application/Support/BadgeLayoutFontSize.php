<?php

namespace App\Modules\BadgePrinting\Application\Support;

final readonly class BadgeLayoutFontSize
{
    /**
     * Resolve CSS pixel font size from layout item relative to badge canvas height.
     *
     * @param  array<string, mixed>  $item
     */
    public function resolveCssPx(array $item, int $canvasHeight): int
    {
        $canvasHeight = max(1, $canvasHeight);
        $percent = $this->resolvePercent($item, $canvasHeight);

        return max(8, (int) round($canvasHeight * $percent / 100));
    }

    /**
     * imagettftext expects points; CSS px are 1/96in, points are 1/72in.
     *
     * @param  array<string, mixed>  $item
     */
    public function resolveGdPoints(array $item, int $canvasHeight): int
    {
        return max(6, (int) round($this->resolveCssPx($item, $canvasHeight) * 0.75));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function resolvePercent(array $item, int $canvasHeight): float
    {
        if (isset($item['fontSizePercent']) && is_numeric($item['fontSizePercent'])) {
            return max(0.5, min(50.0, (float) $item['fontSizePercent']));
        }

        $legacyPx = max(1, (int) ($item['fontSize'] ?? 12));

        return max(0.5, min(50.0, ($legacyPx / max(1, $canvasHeight)) * 100));
    }
}
