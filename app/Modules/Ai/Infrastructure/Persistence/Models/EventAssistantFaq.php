<?php

namespace App\Modules\Ai\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventAssistantFaq extends Model
{
    protected $table = 'event_assistant_faqs';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'question_en',
        'question_ar',
        'answer_en',
        'answer_ar',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function questionFor(string $locale): string
    {
        return $locale === 'ar' ? $this->question_ar : $this->question_en;
    }

    public function answerFor(string $locale): string
    {
        return $locale === 'ar' ? $this->answer_ar : $this->answer_en;
    }
}
