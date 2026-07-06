<?php

namespace App\Modules\Scanning\Infrastructure\Persistence\Models;

use Database\Factories\EventCheckInSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EventCheckInSummary extends Model
{
    /** @use HasFactory<EventCheckInSummaryFactory> */
    use HasFactory;

    protected static function newFactory(): EventCheckInSummaryFactory
    {
        return EventCheckInSummaryFactory::new();
    }

    protected $primaryKey = null;

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'registered_count',
        'checked_in_count',
        'rejected_count',
        'duplicate_count',
        'last_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'last_scan_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
