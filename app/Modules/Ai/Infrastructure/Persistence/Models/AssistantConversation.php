<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssistantConversation extends Model
{
    protected $table = 'assistant_conversations';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'public_id',
        'locale',
        'visitor_hash',
        'started_at',
        'last_activity_at',
        'turn_count',
        'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'turn_count' => 'integer',
            'purge_after' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(AssistantTurn::class, 'conversation_id');
    }
}
