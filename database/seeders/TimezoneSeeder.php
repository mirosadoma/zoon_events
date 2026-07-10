<?php

namespace Database\Seeders;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Timezone;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Seeder;

final class TimezoneSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $identifier) {
            $zone = new DateTimeZone($identifier);
            $parts = explode('/', $identifier, 2);
            $region = $parts[0] ?? $identifier;
            $city = str_replace('_', ' ', $parts[1] ?? $identifier);
            $offsetSeconds = $zone->getOffset(new DateTime('now', $zone));
            $sign = $offsetSeconds >= 0 ? '+' : '-';
            $offsetSeconds = abs($offsetSeconds);
            $hours = intdiv($offsetSeconds, 3600);
            $minutes = intdiv($offsetSeconds % 3600, 60);
            $utcOffset = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

            Timezone::query()->updateOrCreate(
                ['identifier' => $identifier],
                [
                    'name_en' => "{$city} ({$identifier})",
                    'name_ar' => "{$city} ({$identifier})",
                    'region_en' => $region,
                    'region_ar' => $region,
                    'utc_offset' => $utcOffset,
                ],
            );
        }
    }
}
