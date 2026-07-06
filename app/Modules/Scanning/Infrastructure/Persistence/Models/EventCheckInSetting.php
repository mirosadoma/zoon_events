<?php

namespace App\Modules\Scanning\Infrastructure\Persistence\Models;

use Database\Factories\EventCheckInSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EventCheckInSetting extends Model
{
    /** @use HasFactory<EventCheckInSettingFactory> */
    use HasFactory;

    protected static function newFactory(): EventCheckInSettingFactory
    {
        return EventCheckInSettingFactory::new();
    }

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'single_entry_enabled',
        'single_entry_scope',
    ];

    protected function casts(): array
    {
        return [
            'single_entry_enabled' => 'boolean',
        ];
    }
}
