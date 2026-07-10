<?php

namespace App\Modules\AdminConsole\Application;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

final class SiteSettingsRepository
{
    private static ?SiteSetting $cached = null;

    public function current(): SiteSetting
    {
        if (self::$cached instanceof SiteSetting) {
            return self::$cached;
        }

        $settings = SiteSetting::query()->first();

        if (! $settings instanceof SiteSetting) {
            $settings = SiteSetting::query()->create([
                'app_name_en' => config('zonetec.name', 'Zonetec'),
                'app_name_ar' => 'زونتك',
            ]);
        }

        self::$cached = $settings;

        return $settings;
    }

    public function update(array $attributes): SiteSetting
    {
        $settings = $this->current();
        $settings->fill($attributes)->save();
        $this->forgetCache();
        self::$cached = $settings->fresh();

        return self::$cached;
    }

    public function forgetCache(): void
    {
        self::$cached = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $settings = $this->current();

        return [
            'app_name_en' => $settings->app_name_en,
            'app_name_ar' => $settings->app_name_ar,
            'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            'favicon_url' => $settings->favicon_path ? Storage::disk('public')->url($settings->favicon_path) : null,
            'support_email' => $settings->support_email,
            'support_phone' => $settings->support_phone,
            'about_en' => $settings->about_en,
            'about_ar' => $settings->about_ar,
            'maintenance_enabled' => (bool) $settings->maintenance_enabled,
            'maintenance_message_en' => $settings->maintenance_message_en,
            'maintenance_message_ar' => $settings->maintenance_message_ar,
        ];
    }
}
