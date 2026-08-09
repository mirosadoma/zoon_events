<?php

namespace App\Modules\EventSites\Infrastructure\Persistence\Models;

use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $event_id
 * @property int $event_site_id
 * @property int $version
 * @property string $status
 * @property array<int, array<string, mixed>> $blocks
 * @property string $blocks_hash
 * @property int $block_count
 * @property int|null $published_by_user_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class EventSiteVersion extends Model
{
    protected $table = 'event_site_versions';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'event_site_id',
        'version',
        'status',
        'blocks',
        'blocks_hash',
        'block_count',
        'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'version' => 'integer',
            'block_count' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $version): void {
            $dirty = $version->getDirty();
            if (isset($dirty['blocks']) || isset($dirty['blocks_hash'])) {
                throw new RuntimeException(
                    'EventSiteVersion blocks are immutable after creation. Create a new version instead.',
                );
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(EventSite::class, 'event_site_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
        return $query->where('status', 'published');
    }

    public function isLive(): bool
    {
        return $this->status === 'published';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    public static function computeBlocksHash(array $blocks): string
    {
        $normalized = json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $normalized);
    }
}
