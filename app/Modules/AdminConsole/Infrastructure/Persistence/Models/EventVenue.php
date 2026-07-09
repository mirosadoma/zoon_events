<?php

namespace App\Modules\AdminConsole\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventVenue extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'country_id',
        'city_id',
        'name_en',
        'name_ar',
        'location_address',
        'latitude',
        'longitude',
        'start_at',
        'end_at',
        'registration_opens_at',
        'registration_closes_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'registration_opens_at' => 'immutable_datetime',
            'registration_closes_at' => 'immutable_datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
