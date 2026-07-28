<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class EventVenueMap extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'venue_id',
        'image_path',
        'width',
        'height',
        'overlay_opacity',
        'remove_background',
        'show_base_map',
        'map_center_lat',
        'map_center_lng',
        'map_zoom',
        'map_heading',
        'map_type',
        'overlay_north',
        'overlay_south',
        'overlay_east',
        'overlay_west',
        'overlay_rotation',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'overlay_opacity' => 'float',
            'remove_background' => 'boolean',
            'show_base_map' => 'boolean',
            'map_center_lat' => 'float',
            'map_center_lng' => 'float',
            'map_zoom' => 'float',
            'map_heading' => 'float',
            'overlay_north' => 'float',
            'overlay_south' => 'float',
            'overlay_east' => 'float',
            'overlay_west' => 'float',
            'overlay_rotation' => 'float',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EventVenue::class, 'venue_id');
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === '') {
            return null;
        }

        $relativeUrl = Storage::disk('public')->url($this->image_path);

        return str_starts_with($relativeUrl, 'http://') || str_starts_with($relativeUrl, 'https://')
            ? $relativeUrl
            : url($relativeUrl);
    }
}
