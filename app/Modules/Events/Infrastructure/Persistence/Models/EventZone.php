<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Domain\EventZoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventZone extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'venue_id',
        'zone_name_en',
        'zone_name_ar',
        'type',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventZoneType::class,
            'capacity' => 'integer',
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

    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgendaItem::class, 'zone_id');
    }
}
