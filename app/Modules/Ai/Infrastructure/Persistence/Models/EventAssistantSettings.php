<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventAssistantSettings extends Model
{
    protected $table = 'event_assistant_settings';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'enabled',
        'display_name_en',
        'display_name_ar',
        'greeting_en',
        'greeting_ar',
        'fallback_action',
        'fallback_contact_email',
        'daily_question_limit',
        'index_version',
        'indexed_at',
        'index_status',
        'index_error_code',
        'chunk_count',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'daily_question_limit' => 'integer',
            'index_version' => 'integer',
            'indexed_at' => 'immutable_datetime',
            'chunk_count' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
