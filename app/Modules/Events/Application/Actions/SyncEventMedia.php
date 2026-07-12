<?php

namespace App\Modules\Events\Application\Actions;

use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
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

        return $event->refresh()->load('images');
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
}
