<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventKnowledgeChunk extends Model
{
    protected $table = 'event_knowledge_chunks';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'index_version',
        'source_type',
        'source_id',
        'locale',
        'title',
        'content',
        'content_hash',
        'embedding',
        'embedding_model',
        'token_estimate',
    ];

    protected function casts(): array
    {
        return [
            'index_version' => 'integer',
            'embedding' => 'array',
            'token_estimate' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
