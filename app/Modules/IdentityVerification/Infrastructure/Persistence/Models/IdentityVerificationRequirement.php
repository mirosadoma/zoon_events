<?php

namespace App\Modules\IdentityVerification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdentityVerificationRequirement extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'ticket_type_id',
        'level',
        'face_fallback_enabled',
    ];

    protected function casts(): array
    {
        return [
            'face_fallback_enabled' => 'bool',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Events\\Infrastructure\\Persistence\\Models\\Event', 'event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo('App\\Modules\\Ticketing\\Infrastructure\\Persistence\\Models\\TicketType', 'ticket_type_id');
    }
}
