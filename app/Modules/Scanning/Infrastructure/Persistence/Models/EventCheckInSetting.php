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
        'kiosk_offline_threshold_seconds',
        'lookup_confirmation_required',
        'reprint_revokes_old_qr',
        'walk_up_registration_enabled',
        'walk_up_payment_method_enabled',
    ];

    protected function casts(): array
    {
        return [
            'single_entry_enabled' => 'boolean',
            'kiosk_offline_threshold_seconds' => 'integer',
            'lookup_confirmation_required' => 'boolean',
            'reprint_revokes_old_qr' => 'boolean',
            'walk_up_registration_enabled' => 'boolean',
            'walk_up_payment_method_enabled' => 'boolean',
        ];
    }
}
