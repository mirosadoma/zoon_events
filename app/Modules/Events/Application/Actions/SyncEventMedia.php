<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use App\Modules\Events\Infrastructure\Persistence\Models\EventImage;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class SyncEventMedia
{
    public function __construct(private EventMediaPresenter $media) {}

    public function execute(TenantContext $context, Event $event, Request $request): Event
    {
        if ($request->hasFile('main_image')) {
            $this->replaceMainImage($context, $event, $request->file('main_image'));
        }

        if ($request->has('remove_image_ids')) {
            $this->removeGalleryImages($context, $event, $request->input('remove_image_ids', []));
        }

        if ($request->hasFile('images')) {
            $this->appendGalleryImages($context, $event, $request->file('images'));
        }

        $this->syncBrandLogos($context, $event, $request);

        return $event->refresh()->load(['images', 'branding']);
    }

    private function replaceMainImage(TenantContext $context, Event $event, UploadedFile $file): void
    {
        if ($event->main_image_path) {
            Storage::disk('public')->delete($event->main_image_path);
        }

        $path = $file->store("tenants/{$context->tenant->id}/events/{$event->id}/main", 'public');
        $event->forceFill(['main_image_path' => $path])->save();
    }

    private function removeGalleryImages(TenantContext $context, Event $event, mixed $rawIds): void
    {
        $ids = collect(is_array($rawIds) ? $rawIds : [$rawIds])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        $images = EventImage::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->get();

        $this->media->deleteGalleryFiles($images);
        EventImage::query()->whereIn('id', $images->pluck('id'))->delete();
    }

    /** @param array<int, UploadedFile>|UploadedFile $files */
    private function appendGalleryImages(TenantContext $context, Event $event, array|UploadedFile $files): void
    {
        $uploads = is_array($files) ? $files : [$files];
        $nextSort = (int) EventImage::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('event_id', $event->id)
            ->max('sort_order');

        foreach ($uploads as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $nextSort++;
            $path = $file->store("tenants/{$context->tenant->id}/events/{$event->id}/gallery", 'public');

            EventImage::query()->create([
                'tenant_id' => $context->tenant->id,
                'event_id' => $event->id,
                'path' => $path,
                'sort_order' => $nextSort,
            ]);
        }
    }

    private function syncBrandLogos(TenantContext $context, Event $event, Request $request): void
    {
        $logoUpdates = [];

        foreach (['brand_logo' => 'logo_path', 'sponsor_logo' => 'sponsor_logo_path'] as $input => $themeKey) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $file = $request->file($input);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("tenants/{$context->tenant->id}/events/{$event->id}/branding", 'public');
            $logoUpdates[$themeKey] = $path;
        }

        if ($logoUpdates === []) {
            return;
        }

        $branding = EventBranding::query()->firstOrCreate(
            ['tenant_id' => $context->tenant->id, 'event_id' => $event->id],
            [
                'brand_reference' => $event->slug.'-brand',
                'domain_reference' => config('app.url'),
                'content_en' => [],
                'content_ar' => [],
                'sender_name_en' => $event->name_en,
                'sender_name_ar' => $event->name_ar,
                'status' => 'active',
                'theme_config' => [],
            ],
        );

        $theme = is_array($branding->theme_config) ? $branding->theme_config : [];

        foreach ($logoUpdates as $themeKey => $path) {
            $previous = $theme[$themeKey] ?? null;
            if (is_string($previous) && $previous !== '') {
                Storage::disk('public')->delete($previous);
            }
            $theme[$themeKey] = $path;
        }

        $branding->forceFill(['theme_config' => $theme])->save();
    }
}
