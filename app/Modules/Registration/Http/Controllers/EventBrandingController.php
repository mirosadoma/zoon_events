<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class EventBrandingController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly TenantContextStore $contexts,
        private readonly EventScope $events,
    ) {}

    public function show(string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        return $this->success($branding ? [
            'theme_config' => $this->presentTheme($branding->theme_config),
        ] : ['theme_config' => null]);
    }

    public function update(Request $request, string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $modeRules = fn (string $prefix): array => [
            "{$prefix}" => ['sometimes', 'nullable', 'array'],
            "{$prefix}.primary_color" => ['nullable', 'string', 'max:7'],
            "{$prefix}.accent_color" => ['nullable', 'string', 'max:7'],
            "{$prefix}.background_color" => ['nullable', 'string', 'max:7'],
            "{$prefix}.background_mode" => ['nullable', 'string', 'in:solid,gradient,image'],
            "{$prefix}.background_gradient" => ['nullable', 'array'],
            "{$prefix}.background_gradient.type" => ['nullable', 'string', 'in:linear'],
            "{$prefix}.background_gradient.angle" => ['nullable', 'numeric', 'min:0', 'max:360'],
            "{$prefix}.background_gradient.stops" => ['nullable', 'array', 'min:2', 'max:5'],
            "{$prefix}.background_gradient.stops.*.color" => ["required_with:{$prefix}.background_gradient.stops", 'string', 'max:7'],
            "{$prefix}.background_gradient.stops.*.position" => ["required_with:{$prefix}.background_gradient.stops", 'numeric', 'min:0', 'max:100'],
            "{$prefix}.background_image_path" => ['nullable', 'string', 'max:500'],
            "{$prefix}.clear_background_image" => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate([
            'theme_config' => ['required', 'array'],
            ...$modeRules('theme_config.light'),
            ...$modeRules('theme_config.dark'),
            'theme_config.font_family_en' => ['nullable', 'string', 'max:80'],
            'theme_config.font_family_ar' => ['nullable', 'string', 'max:80'],
            // Legacy flat keys (still accepted; mirrored into light when nested absent)
            'theme_config.primary_color' => ['nullable', 'string', 'max:7'],
            'theme_config.accent_color' => ['nullable', 'string', 'max:7'],
            'theme_config.background_color' => ['nullable', 'string', 'max:7'],
            'theme_config.background_mode' => ['nullable', 'string', 'in:solid,gradient,image'],
            'theme_config.background_gradient' => ['nullable', 'array'],
            'theme_config.background_gradient.type' => ['nullable', 'string', 'in:linear'],
            'theme_config.background_gradient.angle' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'theme_config.background_gradient.stops' => ['nullable', 'array', 'min:2', 'max:5'],
            'theme_config.background_gradient.stops.*.color' => ['required_with:theme_config.background_gradient.stops', 'string', 'max:7'],
            'theme_config.background_gradient.stops.*.position' => ['required_with:theme_config.background_gradient.stops', 'numeric', 'min:0', 'max:100'],
            'theme_config.background_image_path' => ['nullable', 'string', 'max:500'],
            'theme_config.clear_background_image' => ['sometimes', 'boolean'],
            'theme_config.text_color' => ['nullable', 'string', 'max:7'],
            'theme_config.font_family' => ['nullable', 'string', 'max:80'],
            'theme_config.logo_path' => ['nullable', 'string', 'max:500'],
            'theme_config.sponsor_logo_path' => ['nullable', 'string', 'max:500'],
            'theme_config.header_image_path' => ['nullable', 'string', 'max:500'],
            'theme_mode' => ['sometimes', 'string', Rule::in(['light', 'dark'])],
        ]);

        $incoming = $validated['theme_config'];
        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $existingTheme = is_array($branding?->theme_config) ? $branding->theme_config : [];
        $theme = $this->normalizeThemeConfig($incoming, $existingTheme);

        if ($branding) {
            $branding->update(['theme_config' => $theme]);
        } else {
            $branding = EventBranding::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'brand_reference' => 'default',
                'domain_reference' => config('app.url'),
                'content_en' => [],
                'content_ar' => [],
                'sender_name_en' => 'Event',
                'sender_name_ar' => 'الفعالية',
                'status' => 'active',
                'theme_config' => $theme,
            ]);
        }

        return $this->success(['theme_config' => $this->presentTheme($branding->theme_config)]);
    }

    public function uploadBackground(Request $request, string $eventId)
    {
        $tenantId = $this->contexts->current()->tenant->id;
        abort_unless($this->events->exists($tenantId, $eventId), 404);

        $validated = $request->validate([
            'background_image' => ['required', 'image', 'max:5120'],
            'theme_mode' => ['sometimes', 'string', Rule::in(['light', 'dark'])],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['background_image'];
        $mode = $validated['theme_mode'] ?? 'light';

        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $theme = $this->normalizeThemeConfig(
            is_array($branding?->theme_config) ? $branding->theme_config : [],
            is_array($branding?->theme_config) ? $branding->theme_config : [],
        );

        $oldPath = is_string($theme[$mode]['background_image_path'] ?? null)
            ? $theme[$mode]['background_image_path']
            : null;
        if (is_string($oldPath) && $oldPath !== '') {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $file->store("tenants/{$tenantId}/events/{$eventId}/registration/backgrounds", 'public');
        $theme[$mode]['background_mode'] = 'image';
        $theme[$mode]['background_image_path'] = $path;

        if ($mode === 'light') {
            $theme['background_mode'] = 'image';
            $theme['background_image_path'] = $path;
        }

        if ($branding) {
            $branding->update(['theme_config' => $theme]);
        } else {
            $branding = EventBranding::create([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'brand_reference' => 'default',
                'domain_reference' => config('app.url'),
                'content_en' => [],
                'content_ar' => [],
                'sender_name_en' => 'Event',
                'sender_name_ar' => 'الفعالية',
                'status' => 'active',
                'theme_config' => $theme,
            ]);
        }

        return $this->success(['theme_config' => $this->presentTheme($branding->theme_config)]);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function normalizeThemeConfig(array $incoming, array $existing): array
    {
        $existingNormalized = $this->structureTheme($existing);
        $incomingNormalized = $this->structureTheme($incoming);

        $light = $this->mergeMode(
            is_array($incomingNormalized['light'] ?? null) ? $incomingNormalized['light'] : [],
            is_array($existingNormalized['light'] ?? null) ? $existingNormalized['light'] : [],
            (bool) ($incoming['light']['clear_background_image'] ?? $incoming['clear_background_image'] ?? false),
        );
        $dark = $this->mergeMode(
            is_array($incomingNormalized['dark'] ?? null) ? $incomingNormalized['dark'] : [],
            is_array($existingNormalized['dark'] ?? null) ? $existingNormalized['dark'] : [],
            (bool) ($incoming['dark']['clear_background_image'] ?? false),
        );

        $fontEn = (string) ($incoming['font_family_en'] ?? $incoming['font_family'] ?? $existingNormalized['font_family_en'] ?? 'Inter');
        $fontAr = (string) ($incoming['font_family_ar'] ?? $existingNormalized['font_family_ar'] ?? $incoming['font_family'] ?? 'Cairo');

        return [
            'light' => $light,
            'dark' => $dark,
            'font_family_en' => $fontEn,
            'font_family_ar' => $fontAr,
            'primary_color' => $light['primary_color'],
            'accent_color' => $light['accent_color'],
            'background_color' => $light['background_color'],
            'background_mode' => $light['background_mode'],
            'background_gradient' => $light['background_gradient'],
            'background_image_path' => $light['background_image_path'],
            'font_family' => $fontEn,
            'text_color' => $incoming['text_color'] ?? $existing['text_color'] ?? null,
            'logo_path' => $incoming['logo_path'] ?? $existing['logo_path'] ?? null,
            'sponsor_logo_path' => $incoming['sponsor_logo_path'] ?? $existing['sponsor_logo_path'] ?? null,
            'header_image_path' => $incoming['header_image_path'] ?? $existing['header_image_path'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function structureTheme(array $theme): array
    {
        $hasNested = isset($theme['light']) || isset($theme['dark']);

        if ($hasNested) {
            return [
                'light' => is_array($theme['light'] ?? null) ? $theme['light'] : [],
                'dark' => is_array($theme['dark'] ?? null) ? $theme['dark'] : [],
                'font_family_en' => $theme['font_family_en'] ?? $theme['font_family'] ?? 'Inter',
                'font_family_ar' => $theme['font_family_ar'] ?? 'Cairo',
            ];
        }

        $flat = [
            'primary_color' => $theme['primary_color'] ?? '#3b82f6',
            'accent_color' => $theme['accent_color'] ?? '#8b5cf6',
            'background_color' => $theme['background_color'] ?? '#ffffff',
            'background_mode' => $theme['background_mode'] ?? 'solid',
            'background_gradient' => $theme['background_gradient'] ?? null,
            'background_image_path' => $theme['background_image_path'] ?? null,
        ];

        return [
            'light' => $flat,
            'dark' => [
                'primary_color' => '#60a5fa',
                'accent_color' => '#a78bfa',
                'background_color' => '#0f172a',
                'background_mode' => 'solid',
                'background_gradient' => null,
                'background_image_path' => null,
            ],
            'font_family_en' => $theme['font_family'] ?? 'Inter',
            'font_family_ar' => 'Cairo',
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeMode(array $incoming, array $existing, bool $clearImage): array
    {
        $merged = [
            'primary_color' => $incoming['primary_color'] ?? $existing['primary_color'] ?? '#3b82f6',
            'accent_color' => $incoming['accent_color'] ?? $existing['accent_color'] ?? '#8b5cf6',
            'background_color' => $incoming['background_color'] ?? $existing['background_color'] ?? '#ffffff',
            'background_mode' => $incoming['background_mode'] ?? $existing['background_mode'] ?? 'solid',
            'background_gradient' => array_key_exists('background_gradient', $incoming)
                ? $incoming['background_gradient']
                : ($existing['background_gradient'] ?? null),
            'background_image_path' => array_key_exists('background_image_path', $incoming)
                ? $incoming['background_image_path']
                : ($existing['background_image_path'] ?? null),
        ];

        if ($clearImage) {
            $oldPath = is_string($existing['background_image_path'] ?? null) ? $existing['background_image_path'] : null;
            if (is_string($oldPath) && $oldPath !== '') {
                Storage::disk('public')->delete($oldPath);
            }
            $merged['background_image_path'] = null;
        }

        return $merged;
    }

    /** @param  mixed  $theme */
    private function presentTheme(mixed $theme): ?array
    {
        if (! is_array($theme)) {
            return null;
        }

        $normalized = $this->normalizeThemeConfig($theme, $theme);

        foreach (['light', 'dark'] as $mode) {
            $path = is_string($normalized[$mode]['background_image_path'] ?? null)
                ? $normalized[$mode]['background_image_path']
                : null;
            if ($path !== null && $path !== '') {
                $url = Storage::disk('public')->url($path);
                $normalized[$mode]['background_image_url'] = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                    ? $url
                    : url($url);
            }
        }

        if (is_string($normalized['background_image_path'] ?? null) && $normalized['background_image_path'] !== '') {
            $url = Storage::disk('public')->url($normalized['background_image_path']);
            $normalized['background_image_url'] = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                ? $url
                : url($url);
        }

        return $normalized;
    }
}
