<?php

namespace App\Modules\IdentityVerification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdentityConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendee_id',
        'notice_version',
        'disclosures',
        'residency_mode',
        'consented_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'disclosures' => 'array',
            'consented_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Attendees\\Infrastructure\\Persistence\\Models\\Attendee', 'attendee_id');
    }
}
