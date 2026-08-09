<?php

namespace App\Modules\EventSites\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Domain\SiteStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $event_id
 * @property string $status
 * @property string $page_mode
 * @property array<int, array<string, mixed>> $draft_blocks
 * @property array<string, mixed>|null $settings
 * @property int|null $draft_updated_by_user_id
 * @property int $draft_revision
 * @property int|null $live_version_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $unpublished_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class EventSite extends Model
{
    protected $table = 'event_sites';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'status',
        'page_mode',
        'draft_blocks',
        'settings',
        'draft_updated_by_user_id',
        'draft_revision',
        'live_version_id',
        'published_at',
        'unpublished_at',
    ];

    protected function casts(): array
    {
        return [
            'draft_blocks' => 'array',
            'settings' => 'array',
            'draft_revision' => 'integer',
            'published_at' => 'immutable_datetime',
            'unpublished_at' => 'immutable_datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function liveVersion(): HasOne
    {
        return $this->hasOne(EventSiteVersion::class, 'id', 'live_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EventSiteVersion::class, 'event_site_id')
            ->orderByDesc('version');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', SiteStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === SiteStatus::Published->value;
    }

    public function getStatusEnum(): SiteStatus
    {
        return SiteStatus::from($this->status);
    }
}
