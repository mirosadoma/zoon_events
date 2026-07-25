<?php

namespace App\Modules\Events\Application\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Rewrites <img src> in HTML email bodies to cid: placeholders and loads image bytes for embedding.
 */
final readonly class PrepareHtmlEmailEmbeddedImages
{
    /**
     * @return array{
     *   html: string,
     *   images: list<array{cid: string, bytes: string, mime: string, filename: string}>
     * }
     */
    public function execute(string $html): array
    {
        if ($html === '' || ! str_contains($html, '<img')) {
            return ['html' => $html, 'images' => []];
        }

        $images = [];
        $index = 0;

        $rewritten = preg_replace_callback(
            '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)\2/i',
            function (array $matches) use (&$images, &$index): string {
                $prefix = $matches[1];
                $quote = $matches[2];
                $src = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5);

                if ($src === '' || str_starts_with($src, 'cid:') || str_starts_with($src, 'data:')) {
                    if (str_starts_with($src, 'data:')) {
                        $loaded = $this->loadDataUri($src);
                        if ($loaded === null) {
                            return $matches[0];
                        }

                        $index++;
                        $cid = 'email-img-'.$index;
                        $images[] = [
                            'cid' => $cid,
                            'bytes' => $loaded['bytes'],
                            'mime' => $loaded['mime'],
                            'filename' => $cid.'.'.$loaded['extension'],
                        ];

                        return $prefix.$quote.'cid:'.$cid.$quote;
                    }

                    return $matches[0];
                }

                $loaded = $this->loadImage($src);
                if ($loaded === null) {
                    return $matches[0];
                }

                $index++;
                $cid = 'email-img-'.$index;
                $images[] = [
                    'cid' => $cid,
                    'bytes' => $loaded['bytes'],
                    'mime' => $loaded['mime'],
                    'filename' => $cid.'.'.$loaded['extension'],
                ];

                return $prefix.$quote.'cid:'.$cid.$quote;
            },
            $html,
        );

        return [
            'html' => is_string($rewritten) ? $rewritten : $html,
            'images' => $images,
        ];
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function loadImage(string $src): ?array
    {
        if (preg_match('#^https?://#i', $src) === 1) {
            return $this->loadRemoteImage($src);
        }

        return $this->loadImageFromStoragePath($src);
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function loadRemoteImage(string $src): ?array
    {
        $path = parse_url($src, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $fromStorage = $this->loadImageFromStoragePath($path);
            if ($fromStorage !== null) {
                return $fromStorage;
            }
        }

        try {
            $response = Http::timeout(5)->get($src);
            if (! $response->successful()) {
                return null;
            }

            $mime = explode(';', (string) ($response->header('Content-Type') ?: 'image/png'))[0];

            return [
                'bytes' => $response->body(),
                'mime' => $mime !== '' ? $mime : 'image/png',
                'extension' => $this->extensionForMime($mime),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function loadImageFromStoragePath(string $path): ?array
    {
        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }

        $absolute = Storage::disk('public')->path($relative);
        if (! is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';

        return [
            'bytes' => (string) file_get_contents($absolute),
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
        ];
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function loadDataUri(string $src): ?array
    {
        if (! preg_match('#^data:([^;,]+)?(?:;base64)?,(.+)$#s', $src, $matches)) {
            return null;
        }

        $mime = trim((string) ($matches[1] ?? 'image/png'));
        if ($mime === '') {
            $mime = 'image/png';
        }

        $payload = str_contains($src, ';base64,')
            ? base64_decode($matches[2], true)
            : rawurldecode($matches[2]);

        if ($payload === false || $payload === '') {
            return null;
        }

        return [
            'bytes' => $payload,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
        ];
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower(explode(';', $mime)[0])) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }
}
