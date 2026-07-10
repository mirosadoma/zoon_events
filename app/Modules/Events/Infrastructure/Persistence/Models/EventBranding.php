<?php

namespace App\Modules\Events\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class EventBranding extends Model
{
    protected $table = 'event_branding';

    protected $fillable = [
        'tenant_id', 'event_id', 'brand_reference', 'domain_reference', 'content_en',
        'content_ar', 'sender_name_en', 'sender_name_ar', 'status',
    ];

    protected function casts(): array
    {
        return ['content_en' => 'array', 'content_ar' => 'array'];
    }
}
