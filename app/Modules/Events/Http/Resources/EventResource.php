<?php

namespace App\Modules\Events\Http\Resources;

use App\Modules\Events\Application\Support\EventMediaPresenter;
use App\Modules\Events\Application\Support\EventWallClockDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(EventMediaPresenter::class)->forSetup($this->resource);
        $timezone = (string) $this->timezone;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => ['en' => $this->name_en, 'ar' => $this->name_ar],
            'description' => ['en' => $this->description_en, 'ar' => $this->description_ar],
            'tier' => $this->tier,
            'status' => $this->status,
            'timezone' => $timezone,
            'start_at' => EventWallClockDateTime::toIso8601($this->start_at, $timezone),
            'end_at' => EventWallClockDateTime::toIso8601($this->end_at, $timezone),
            'registration_opens_at' => EventWallClockDateTime::toIso8601($this->registration_opens_at, $timezone),
            'registration_closes_at' => EventWallClockDateTime::toIso8601($this->registration_closes_at, $timezone),
            'capacity' => $this->capacity,
            'main_image' => $media['main_image'],
            'images' => $media['images'],
        ];
    }
}
