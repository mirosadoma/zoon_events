<?php

namespace App\Modules\Registration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Events\Contracts\EventScope;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use App\Modules\Tenancy\Domain\Context\TenantContextStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $validated = $request->validate([
            'theme_config' => ['required', 'array'],
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
        ]);

        $theme = $validated['theme_config'];
        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $existingTheme = is_array($branding?->theme_config) ? $branding->theme_config : [];

        if (! empty($theme['clear_background_image'])) {
            $oldPath = is_string($existingTheme['background_image_path'] ?? null)
                ? $existingTheme['background_image_path']
                : null;
            if (is_string($oldPath) && $oldPath !== '') {
                Storage::disk('public')->delete($oldPath);
            }
            $theme['background_image_path'] = null;
        } elseif (! array_key_exists('background_image_path', $theme)) {
            $theme['background_image_path'] = $existingTheme['background_image_path'] ?? null;
        }

        unset($theme['clear_background_image']);

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
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['background_image'];

        $branding = EventBranding::query()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->first();

        $theme = is_array($branding?->theme_config) ? $branding->theme_config : [];
        if (is_string($theme['background_image_path'] ?? null) && $theme['background_image_path'] !== '') {
            Storage::disk('public')->delete($theme['background_image_path']);
        }

        $path = $file->store("tenants/{$tenantId}/events/{$eventId}/registration/backgrounds", 'public');
        $theme['background_mode'] = 'image';
        $theme['background_image_path'] = $path;

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

    /** @param  mixed  $theme */
    private function presentTheme(mixed $theme): ?array
    {
        if (! is_array($theme)) {
            return null;
        }

        $path = is_string($theme['background_image_path'] ?? null) ? $theme['background_image_path'] : null;
        if ($path !== null && $path !== '') {
            $url = Storage::disk('public')->url($path);
            $theme['background_image_url'] = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
                ? $url
                : url($url);
        }

        return $theme;
    }
}
