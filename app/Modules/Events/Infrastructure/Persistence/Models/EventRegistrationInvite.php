<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationInvite extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'email',
        'code',
        'is_active',
        'invite_status',
        'sent_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sent_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
