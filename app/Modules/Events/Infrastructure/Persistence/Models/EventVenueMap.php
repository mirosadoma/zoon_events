<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
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
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
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
