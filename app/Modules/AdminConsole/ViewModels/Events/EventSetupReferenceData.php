<?php

namespace App\Modules\AdminConsole\ViewModels\Events;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Timezone;

final readonly class EventSetupReferenceData
{
    /** @return array{timezones:list<array<string,string>>,countries:list<array<string,mixed>>} */
    public function toArray(): array
    {
        return [
            'timezones' => Timezone::query()
                ->orderBy('region_en')
                ->orderBy('name_en')
                ->get(['identifier', 'name_en', 'name_ar', 'region_en', 'country_en', 'country_ar', 'utc_offset'])
                ->map(fn (Timezone $timezone): array => [
                    'identifier' => $timezone->identifier,
                    'name_en' => $timezone->name_en,
                    'name_ar' => $timezone->name_ar,
                    'region_en' => $timezone->region_en ?? '',
                    'country_en' => $timezone->country_en ?? '',
                    'country_ar' => $timezone->country_ar ?? '',
                    'utc_offset' => $timezone->utc_offset,
                ])
                ->values()
                ->all(),
            'countries' => Country::query()
                ->where('is_active', true)
                ->orderBy('name_en')
                ->with(['cities' => fn ($query) => $query->where('is_active', true)->orderBy('name_en')])
                ->get()
                ->map(fn (Country $country): array => [
                    'id' => (string) $country->id,
                    'code' => $country->code,
                    'name_en' => $country->name_en,
                    'name_ar' => $country->name_ar,
                    'cities' => $country->cities->map(fn (City $city): array => [
                        'id' => (string) $city->id,
                        'name_en' => $city->name_en,
                        'name_ar' => $city->name_ar,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
