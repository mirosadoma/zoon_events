<?php

namespace App\Modules\BadgePrinting\Application\Support;

use GdImage;

final readonly class BadgeBackgroundRenderer
{
    /**
     * @param  array<string, mixed>|null  $gradient
     * @return array{type:string,angle:float,stops:list<array{color:string,position:float>}>|null
     */
    public function normalize(?array $gradient): ?array
    {
        if ($gradient === null || ($gradient['type'] ?? 'linear') !== 'linear') {
            return null;
        }

        $rawStops = $gradient['stops'] ?? null;
        if (! is_array($rawStops) || count($rawStops) < 2) {
            return null;
        }

        $stops = [];
        foreach ($rawStops as $stop) {
            if (! is_array($stop)) {
                continue;
            }

            $color = $this->parseHex(is_string($stop['color'] ?? null) ? $stop['color'] : null);
            if ($color === null) {
                continue;
            }

            $position = max(0.0, min(100.0, (float) ($stop['position'] ?? 0)));
            $stops[] = [
                'color' => $color,
                'position' => $position,
            ];
        }

        if (count($stops) < 2) {
            return null;
        }

        usort($stops, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return [
            'type' => 'linear',
            'angle' => fmod((float) ($gradient['angle'] ?? 180) + 360, 360),
            'stops' => $stops,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $gradient
     */
    public function toCss(?string $solidColor, ?array $gradient): string
    {
        $normalized = $this->normalize($gradient);
        if ($normalized !== null) {
            $parts = [];
            foreach ($normalized['stops'] as $stop) {
                $parts[] = sprintf('%s %.2f%%', $stop['color'], $stop['position']);
            }

            return sprintf(
                'linear-gradient(%.2fdeg, %s)',
                $normalized['angle'],
                implode(', ', $parts),
            );
        }

        return $this->parseHex($solidColor) ?? '#ffffff';
    }

    /**
     * @param  array<string, mixed>|null  $gradient
     * @return array{r:int,g:int,b:int}
     */
    public function fallbackRgb(?string $solidColor, ?array $gradient): array
    {
        $normalized = $this->normalize($gradient);
        if ($normalized !== null) {
            $rgb = $this->parseHexRgb($normalized['stops'][0]['color']);

            return $rgb ?? ['r' => 255, 'g' => 255, 'b' => 255];
        }

        return $this->parseHexRgb($this->parseHex($solidColor) ?? '#ffffff') ?? ['r' => 255, 'g' => 255, 'b' => 255];
    }

    /**
     * @param  array<string, mixed>|null  $gradient
     */
    public function draw(GdImage $canvas, int $width, int $height, ?string $solidColor, ?array $gradient): void
    {
        $normalized = $this->normalize($gradient);
        if ($normalized === null) {
            $rgb = $this->fallbackRgb($solidColor, null);
            $fill = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $fill);

            return;
        }

        $angleRad = deg2rad($normalized['angle'] - 90);
        $cos = cos($angleRad);
        $sin = sin($angleRad);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $nx = ($width <= 1) ? 0.0 : ($x / ($width - 1)) * 2 - 1;
                $ny = ($height <= 1) ? 0.0 : ($y / ($height - 1)) * 2 - 1;
                $t = max(0.0, min(1.0, 0.5 + (($nx * $cos + $ny * $sin) * 0.5)));
                $rgb = $this->colorAtPosition($normalized['stops'], $t * 100);
                $color = imagecolorallocate($canvas, $rgb['r'], $rgb['g'], $rgb['b']);
                imagesetpixel($canvas, $x, $y, $color);
            }
        }
    }

    /** @return array{r:int,g:int,b:int} */
    private function colorAtPosition(array $stops, float $position): array
    {
        $first = $stops[0];
        $last = $stops[count($stops) - 1];

        if ($position <= $first['position']) {
            return $this->parseHexRgb($first['color']) ?? ['r' => 255, 'g' => 255, 'b' => 255];
        }

        if ($position >= $last['position']) {
            return $this->parseHexRgb($last['color']) ?? ['r' => 255, 'g' => 255, 'b' => 255];
        }

        for ($index = 0; $index < count($stops) - 1; $index++) {
            $start = $stops[$index];
            $end = $stops[$index + 1];

            if ($position < $start['position'] || $position > $end['position']) {
                continue;
            }

            $span = max(0.0001, $end['position'] - $start['position']);
            $ratio = ($position - $start['position']) / $span;
            $startRgb = $this->parseHexRgb($start['color']) ?? ['r' => 0, 'g' => 0, 'b' => 0];
            $endRgb = $this->parseHexRgb($end['color']) ?? ['r' => 255, 'g' => 255, 'b' => 255];

            return [
                'r' => (int) round($startRgb['r'] + (($endRgb['r'] - $startRgb['r']) * $ratio)),
                'g' => (int) round($startRgb['g'] + (($endRgb['g'] - $startRgb['g']) * $ratio)),
                'b' => (int) round($startRgb['b'] + (($endRgb['b'] - $startRgb['b']) * $ratio)),
            ];
        }

        return $this->parseHexRgb($last['color']) ?? ['r' => 255, 'g' => 255, 'b' => 255];
    }

    private function parseHex(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
            return null;
        }

        $hex = $matches[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.strtolower($hex);
    }

    /** @return array{r:int,g:int,b:int}|null */
    private function parseHexRgb(string $hex): ?array
    {
        $normalized = $this->parseHex($hex);
        if ($normalized === null) {
            return null;
        }

        $value = substr($normalized, 1);

        return [
            'r' => hexdec(substr($value, 0, 2)),
            'g' => hexdec(substr($value, 2, 2)),
            'b' => hexdec(substr($value, 4, 2)),
        ];
    }
}
