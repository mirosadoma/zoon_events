<?php

namespace App\Modules\AdminConsole\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class SiteSetting extends Model
{
    protected $fillable = [
        'app_name_en',
        'app_name_ar',
        'logo_path',
        'favicon_path',
        'support_email',
        'support_phone',
        'about_en',
        'about_ar',
        'maintenance_enabled',
        'maintenance_message_en',
        'maintenance_message_ar',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_enabled' => 'boolean',
        ];
    }
}
