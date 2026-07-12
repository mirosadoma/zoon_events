<?php

namespace App\Modules\Events\Application\Support;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final readonly class EventMediaPresenter
{
    /** @return array{main_image: ?string, images: list<string>} */
    public function forRegistration(Event $event): array
    {
        $mainImage = $this->url($event->main_image_path);
        $gallery = $event->relationLoaded('images')
            ? $event->images
            : $event->images()->orderBy('sort_order')->orderBy('id')->get();

        $images = $gallery
            ->map(fn (EventImage $image): ?string => $this->url($image->path))
            ->filter(fn (?string $url): bool => $url !== null)
            ->values()
            ->all();

        if ($mainImage === null && $images === []) {
            return ['main_image' => null, 'images' => []];
        }

        return [
            'main_image' => $mainImage,
            'images' => $images,
        ];
    }

    /** @return array{main_image: ?array{id:null,url:string,path:string}, images: list<array{id:string,url:string,path:string,sort_order:int}>} */
    public function forSetup(Event $event): array
    {
        $gallery = $event->relationLoaded('images')
            ? $event->images
            : $event->images()->orderBy('sort_order')->orderBy('id')->get();

        return [
            'main_image' => $event->main_image_path
                ? [
                    'id' => null,
                    'url' => $this->url($event->main_image_path),
                    'path' => $event->main_image_path,
                ]
                : null,
            'images' => $gallery->map(fn (EventImage $image): array => [
                'id' => (string) $image->id,
                'url' => $this->url($image->path),
                'path' => $image->path,
                'sort_order' => (int) $image->sort_order,
            ])->values()->all(),
        ];
    }

    public function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /** @param Collection<int, EventImage> $images */
    public function deleteGalleryFiles(Collection $images): void
    {
        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
        }
    }
}
