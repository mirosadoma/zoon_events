<?php

namespace App\Modules\AdminConsole\ViewModels\Badges;

use App\Modules\BadgePrinting\Application\Support\ResolveBadgeFormFieldKeys;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Application\Support\EventVenuePresenter;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class BadgeTemplatePageViewModel
{
    public function __construct(
        private ResolveBadgeFormFieldKeys $formFieldKeys,
        private EventMediaPresenter $media,
        private EventVenuePresenter $venues,
    ) {}

    /**
     * @param  Collection<int, BadgeTemplate>  $templates
     * @return array{event: array<string, mixed>, tenantId: string, templates: list<array<string, mixed>>, registrationFields: list<array<string, mixed>>}
     */
    public function index(Event $event, string $tenantId, Collection $templates): array
    {
        return [
            'event' => $this->eventRow($event),
            'tenantId' => $tenantId,
            'registrationFields' => $this->formFieldKeys->forEvent($event),
            'templates' => $templates->map(fn (BadgeTemplate $template): array => [
                'id' => (string) $template->id,
                'name' => $template->name,
                'layout' => $template->layout,
                'paper_size' => $template->paper_size,
                'printer_type' => $template->printer_type,
                'orientation' => $template->orientation ?? 'portrait',
                'background_color' => $template->background_color,
                'background_gradient' => $template->background_gradient,
                'background_image_path' => $template->background_image_path,
                'background_image_url' => $this->backgroundImageUrl($template->background_image_path),
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'status' => $template->status,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function eventRow(Event $event): array
    {
        $media = $this->media->forRegistration($event->loadMissing('images'));
        $venues = $this->venues->forEvent($event);
        $theme = $this->themeConfig((string) $event->tenant_id, (string) $event->id);
        $logoUrl = $this->publicUrl(is_string($theme['logo_path'] ?? null) ? $theme['logo_path'] : null);
        $sponsorLogoUrl = $this->publicUrl(is_string($theme['sponsor_logo_path'] ?? null) ? $theme['sponsor_logo_path'] : null);

        return [
            'id' => (string) $event->id,
            'name' => ['en' => $event->name_en, 'ar' => $event->name_ar],
            'description' => [
                'en' => $event->description_en ?? '',
                'ar' => $event->description_ar ?? '',
            ],
            'timezone' => $event->timezone,
            'start_at' => EventWallClockDateTime::toIso8601($event->start_at, $event->timezone),
            'end_at' => EventWallClockDateTime::toIso8601($event->end_at, $event->timezone),
            'main_image' => $media['main_image'],
            'logo_url' => $logoUrl ?? $media['main_image'],
            'sponsor_logo_url' => $sponsorLogoUrl,
            'images' => $media['images'],
            'venues' => $venues,
            'tier' => $event->tier,
        ];
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

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    private function backgroundImageUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
