<?php

namespace Database\Seeders;

use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use Illuminate\Database\Seeder;

final class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'EG', 'name_en' => 'Egypt', 'name_ar' => 'مصر', 'cities' => [
                ['name_en' => 'Cairo', 'name_ar' => 'القاهرة'],
                ['name_en' => 'Alexandria', 'name_ar' => 'الإسكندرية'],
                ['name_en' => 'Giza', 'name_ar' => 'الجيزة'],
                ['name_en' => 'Sharm El Sheikh', 'name_ar' => 'شرم الشيخ'],
            ]],
            ['code' => 'SA', 'name_en' => 'Saudi Arabia', 'name_ar' => 'المملكة العربية السعودية', 'cities' => [
                ['name_en' => 'Riyadh', 'name_ar' => 'الرياض'],
                ['name_en' => 'Jeddah', 'name_ar' => 'جدة'],
                ['name_en' => 'Dammam', 'name_ar' => 'الدمام'],
                ['name_en' => 'Makkah', 'name_ar' => 'مكة المكرمة'],
            ]],
            ['code' => 'AE', 'name_en' => 'United Arab Emirates', 'name_ar' => 'الإمارات العربية المتحدة', 'cities' => [
                ['name_en' => 'Dubai', 'name_ar' => 'دبي'],
                ['name_en' => 'Abu Dhabi', 'name_ar' => 'أبوظبي'],
                ['name_en' => 'Sharjah', 'name_ar' => 'الشارقة'],
            ]],
            ['code' => 'JO', 'name_en' => 'Jordan', 'name_ar' => 'الأردن', 'cities' => [
                ['name_en' => 'Amman', 'name_ar' => 'عمّان'],
                ['name_en' => 'Aqaba', 'name_ar' => 'العقبة'],
            ]],
            ['code' => 'KW', 'name_en' => 'Kuwait', 'name_ar' => 'الكويت', 'cities' => [
                ['name_en' => 'Kuwait City', 'name_ar' => 'مدينة الكويت'],
            ]],
            ['code' => 'BH', 'name_en' => 'Bahrain', 'name_ar' => 'البحرين', 'cities' => [
                ['name_en' => 'Manama', 'name_ar' => 'المنامة'],
            ]],
        ];

        foreach ($countries as $countryData) {
            $country = Country::query()->updateOrCreate(
                ['code' => $countryData['code']],
                ['name_en' => $countryData['name_en'], 'name_ar' => $countryData['name_ar'], 'is_active' => true],
            );

            foreach ($countryData['cities'] as $cityData) {
                City::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name_en' => $cityData['name_en']],
                    ['name_ar' => $cityData['name_ar'], 'is_active' => true],
                );
            }
        }
    }
}
