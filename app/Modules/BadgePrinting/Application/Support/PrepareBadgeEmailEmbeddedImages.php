<?php

namespace App\Modules\BadgePrinting\Application\Support;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final readonly class PrepareBadgeEmailEmbeddedImages
{
    /**
     * Replace remote/storage image sources with cid: placeholders for email clients.
     *
     * @param  array<string, string|null>  $fields
     * @return array{
     *   fields: array<string, string|null>,
     *   template: BadgeTemplate,
     *   images: list<array{cid: string, bytes: string, mime: string, filename: string}>
     * }
     */
    public function execute(BadgeTemplate $template, array $fields): array
    {
        $images = [];

        $fields = $this->replaceFieldImage($fields, 'organizer_logo_ref', 'badge-organizer-logo', $images);
        $fields = $this->replaceFieldImage($fields, 'sponsor_logo_ref', 'badge-sponsor-logo', $images);
        $template = $this->replaceBackgroundImage($template, 'badge-background', $images);

        return [
            'fields' => $fields,
            'template' => $template,
            'images' => $images,
        ];
    }

    /**
     * @param  array<string, string|null>  $fields
     * @param  list<array{cid: string, bytes: string, mime: string, filename: string}>  $images
     * @return array<string, string|null>
     */
    private function replaceFieldImage(array $fields, string $key, string $cid, array &$images): array
    {
        $src = $fields[$key] ?? null;
        if (! is_string($src) || trim($src) === '' || str_starts_with($src, 'cid:')) {
            return $fields;
        }

        $loaded = $this->loadImage($src);
        if ($loaded === null) {
            $fields[$key] = null;

            return $fields;
        }

        $images[] = [
            'cid' => $cid,
            'bytes' => $loaded['bytes'],
            'mime' => $loaded['mime'],
            'filename' => $cid.'.'.$loaded['extension'],
        ];
        $fields[$key] = 'cid:'.$cid;

        return $fields;
    }

    /**
     * @param  list<array{cid: string, bytes: string, mime: string, filename: string}>  $images
     */
    private function replaceBackgroundImage(BadgeTemplate $template, string $cid, array &$images): BadgeTemplate
    {
        $path = $template->background_image_path;
        if (! is_string($path) || trim($path) === '') {
            return $template;
        }

        if (str_starts_with($path, 'cid:')) {
            return $template;
        }

        $loaded = str_starts_with($path, 'data:')
            ? $this->loadDataUri($path)
            : $this->loadImageFromStoragePath($path);

        if ($loaded === null) {
            return $template;
        }

        $images[] = [
            'cid' => $cid,
            'bytes' => $loaded['bytes'],
            'mime' => $loaded['mime'],
            'filename' => $cid.'.'.$loaded['extension'],
        ];

        $clone = clone $template;
        $clone->background_image_path = 'cid:'.$cid;

        return $clone;
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function loadImage(string $src): ?array
    {
        if (str_starts_with($src, 'data:')) {
            return $this->loadDataUri($src);
        }

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
