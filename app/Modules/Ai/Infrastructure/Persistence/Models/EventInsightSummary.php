<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use App\Models\User;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventInsightSummary extends Model
{
    protected $table = 'event_insight_summaries';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'metric_window',
        'payload_hash',
        'metrics_payload',
        'summary_en',
        'summary_ar',
        'highlights',
        'provider_key',
        'generated_by_user_id',
        'generated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics_payload' => 'array',
            'highlights' => 'array',
            'generated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
