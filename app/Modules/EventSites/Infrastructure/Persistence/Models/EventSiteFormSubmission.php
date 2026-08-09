<?php

namespace App\Modules\EventSites\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $event_id
 * @property int $event_site_id
 * @property string $page_id
 * @property string|null $page_title
 * @property string $block_id
 * @property string|null $form_name
 * @property array $payload
 * @property string|null $visitor_hash
 * @property string $locale
 */
final class EventSiteFormSubmission extends Model
{
    protected $table = 'event_site_form_submissions';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_site_id',
        'page_id',
        'page_title',
        'block_id',
        'form_name',
        'payload',
        'visitor_hash',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
