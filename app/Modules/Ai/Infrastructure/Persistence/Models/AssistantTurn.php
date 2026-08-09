<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssistantTurn extends Model
{
    protected $table = 'assistant_turns';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'conversation_id',
        'question',
        'answer',
        'outcome',
        'citations',
        'provider_key',
        'latency_ms',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'citations' => 'array',
            'latency_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }
}
