<?php

namespace App\Modules\BadgePrinting\Application\Actions;

use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use Illuminate\Support\Facades\Storage;

final readonly class PreviewBadgeTemplateWithTestDataAction
{
    public function __construct(
        private RenderBadgePngAction $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $fieldValues
     * @return array{png_base64: string, mime: string}|null
     */
    public function execute(BadgeTemplate $template, array $fieldValues, ?string $qrPayload = null): ?array
    {
        $normalized = [];
        foreach ($fieldValues as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (is_string($value) || is_numeric($value)) {
                $normalized[$key] = trim((string) $value);
            } elseif ($value === null) {
                $normalized[$key] = null;
            }
        }

        $normalized = $this->ensureBrandLogoUrls($template, $normalized);

        $qr = $qrPayload ?? (is_string($normalized['qr'] ?? null) ? $normalized['qr'] : 'PREVIEW-QR-TEST');
        $png = $this->renderer->execute($template, $normalized, $qr);

        if ($png === null) {
            return null;
        }

        return [
            'png_base64' => base64_encode($png),
            'mime' => 'image/png',
        ];
    }

    /**
     * Designer preview omits logo URLs from typed field_values; resolve them
     * from event branding (same fallback as print payload / canvas preview).
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function ensureBrandLogoUrls(BadgeTemplate $template, array $fields): array
    {
        $needsOrganizer = $this->layoutHasField($template, 'organizer_logo_ref')
            && $this->isBlank($fields['organizer_logo_ref'] ?? null);
        $needsSponsor = $this->layoutHasField($template, 'sponsor_logo_ref')
            && $this->isBlank($fields['sponsor_logo_ref'] ?? null);

        if (! $needsOrganizer && ! $needsSponsor) {
            return $fields;
        }

        $tenantId = (string) ($template->tenant_id ?? '');
        $eventId = (string) ($template->event_id ?? '');
        if ($tenantId === '' || $eventId === '') {
            return $fields;
        }

        $theme = $this->themeConfig($tenantId, $eventId);

        if ($needsOrganizer) {
            $fields['organizer_logo_ref'] = $this->resolveOrganizerLogoUrl($tenantId, $eventId, $theme);
        }

        if ($needsSponsor) {
            $sponsorPath = is_string($theme['sponsor_logo_path'] ?? null) ? $theme['sponsor_logo_path'] : null;
            $fields['sponsor_logo_ref'] = $this->publicUrl($sponsorPath);
        }

        return $fields;
    }

    private function layoutHasField(BadgeTemplate $template, string $fieldKey): bool
    {
        $layout = is_array($template->layout) ? $template->layout : [];

        if ($layout === []) {
            return false;
        }

        if (array_is_list($layout)) {
            foreach ($layout as $item) {
                if (is_array($item) && ($item['field'] ?? null) === $fieldKey) {
                    return true;
                }
            }

            return false;
        }

        return array_key_exists($fieldKey, $layout);
    }

    private function isBlank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function resolveOrganizerLogoUrl(string $tenantId, string $eventId, array $theme): ?string
    {
        $logoPath = is_string($theme['logo_path'] ?? null) ? $theme['logo_path'] : null;
        if (is_string($logoPath) && trim($logoPath) !== '') {
            return $this->publicUrl($logoPath);
        }

        $event = Event::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $eventId)
            ->first(['main_image_path']);

        $mainImagePath = is_string($event?->main_image_path) ? $event->main_image_path : null;

        return $this->publicUrl($mainImagePath);
    }

    /** @return array<string, mixed> */
    private function themeConfig(string $tenantId, string $eventId): array
    {
        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return is_array($branding?->theme_config) ? $branding->theme_config : [];
    }

    private function publicUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
