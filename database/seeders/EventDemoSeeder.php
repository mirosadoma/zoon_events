<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\City;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\Country;
use App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue;
use App\Modules\Events\Infrastructure\Persistence\Models\CategoryTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Events\Infrastructure\Persistence\Models\EventCategory;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Seeder;

final class EventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->first();

        if ($tenant) {
            $this->seedCategoryTemplates($tenant);
            $this->seedEvents($tenant);
        }
    }

    private function seedCategoryTemplates(Tenant $tenant): void
    {
        $templates = [
            ['name' => 'Normal', 'name_ar' => 'عادي', 'slug' => 'normal', 'color' => '#6B7280', 'privileges' => [
                ['key' => 'general_entry', 'label' => 'General Entry', 'label_ar' => 'دخول عام', 'effect' => 'allow', 'target_type' => 'gate', 'target_id' => null],
            ]],
            ['name' => 'VIP', 'name_ar' => 'كبار الشخصيات', 'slug' => 'vip', 'color' => '#F59E0B', 'privileges' => [
                ['key' => 'priority_entry', 'label' => 'Priority Entry', 'label_ar' => 'دخول أولوية', 'effect' => 'allow', 'target_type' => 'gate', 'target_id' => null],
                ['key' => 'free_parking', 'label' => 'Free Parking', 'label_ar' => 'مواقف مجانية', 'effect' => 'allow', 'target_type' => 'parking', 'target_id' => null],
                ['key' => 'vip_lounge', 'label' => 'VIP Lounge Access', 'label_ar' => 'دخول صالة VIP', 'effect' => 'allow', 'target_type' => 'zone', 'target_id' => null],
            ]],
            ['name' => 'VVIP', 'name_ar' => 'كبار كبار الشخصيات', 'slug' => 'vvip', 'color' => '#8B5CF6', 'privileges' => [
                ['key' => 'priority_entry', 'label' => 'Priority Entry', 'label_ar' => 'دخول أولوية', 'effect' => 'allow', 'target_type' => 'gate', 'target_id' => null],
                ['key' => 'free_parking', 'label' => 'Free Parking', 'label_ar' => 'مواقف مجانية', 'effect' => 'allow', 'target_type' => 'parking', 'target_id' => null],
                ['key' => 'vip_lounge', 'label' => 'VIP Lounge Access', 'label_ar' => 'دخول صالة VIP', 'effect' => 'allow', 'target_type' => 'zone', 'target_id' => null],
                ['key' => 'backstage_access', 'label' => 'Backstage Access', 'label_ar' => 'دخول الكواليس', 'effect' => 'allow', 'target_type' => 'zone', 'target_id' => null],
                ['key' => 'priority_parking', 'label' => 'Priority Parking', 'label_ar' => 'مواقف أولوية', 'effect' => 'allow', 'target_type' => 'parking', 'target_id' => null],
            ]],
            ['name' => 'Speaker', 'name_ar' => 'متحدث', 'slug' => 'speaker', 'color' => '#10B981', 'privileges' => [
                ['key' => 'priority_entry', 'label' => 'Priority Entry', 'label_ar' => 'دخول أولوية', 'effect' => 'allow', 'target_type' => 'gate', 'target_id' => null],
                ['key' => 'backstage_access', 'label' => 'Backstage Access', 'label_ar' => 'دخول الكواليس', 'effect' => 'allow', 'target_type' => 'zone', 'target_id' => null],
                ['key' => 'free_parking', 'label' => 'Free Parking', 'label_ar' => 'مواقف مجانية', 'effect' => 'allow', 'target_type' => 'parking', 'target_id' => null],
            ]],
        ];

        foreach ($templates as $i => $tpl) {
            $template = CategoryTemplate::query()->withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $tpl['slug']],
                ['name' => $tpl['name'], 'name_ar' => $tpl['name_ar'], 'color' => $tpl['color'], 'sort_order' => $i],
            );

            $template->privileges()->delete();
            foreach ($tpl['privileges'] as $priv) {
                $template->privileges()->create($priv);
            }
        }
    }

    private function seedEvents(Tenant $tenant): void
    {
        $cairo = $this->city('EG', 'Cairo');
        $riyadh = $this->city('SA', 'Riyadh');
        $dubai = $this->city('AE', 'Dubai');
        $jeddah = $this->city('SA', 'Jeddah');

        $events = [
            [
                'name_en' => 'Annual Tech Conference 2026',
                'name_ar' => 'المؤتمر التقني السنوي 2026',
                'description_en' => 'A two-day technology conference covering AI, cloud, and digital transformation with keynotes, workshops, and networking.',
                'description_ar' => 'مؤتمر تقني لمدة يومين يغطي الذكاء الاصطناعي والسحابة والتحول الرقمي مع كلمات رئيسية وورش عمل وفرص للتواصل.',
                'slug' => 'tech-conf-2026',
                'event_type' => 'conference',
                'registration_mode' => 'paid_ticketing',
                'tier' => 'public',
                'categories' => [
                    ['name' => 'Normal', 'slug' => 'normal', 'capacity' => 500],
                    ['name' => 'VIP', 'slug' => 'vip', 'capacity' => 50],
                    ['name' => 'VVIP', 'slug' => 'vvip', 'capacity' => 10],
                    ['name' => 'Speaker', 'slug' => 'speaker', 'capacity' => 30],
                ],
                'venues' => [
                    [
                        'name_en' => 'Riyadh International Convention Center',
                        'name_ar' => 'مركز الرياض الدولي للمؤتمرات',
                        'city' => $riyadh,
                        'location_address' => 'King Abdullah Financial District, Riyadh 13519, Saudi Arabia',
                        'latitude' => 24.7621000,
                        'longitude' => 46.6403000,
                        'start_offset_days' => 30,
                        'end_offset_days' => 30,
                        'reg_open_offset_days' => 1,
                        'reg_close_offset_days' => 29,
                    ],
                    [
                        'name_en' => 'Innovation Hall Annex',
                        'name_ar' => 'قاعة الابتكار الملحقة',
                        'city' => $riyadh,
                        'location_address' => 'KAFD Boulevard, Building A2, Riyadh 13519, Saudi Arabia',
                        'latitude' => 24.7645000,
                        'longitude' => 46.6428000,
                        'start_offset_days' => 31,
                        'end_offset_days' => 31,
                        'reg_open_offset_days' => 1,
                        'reg_close_offset_days' => 30,
                    ],
                ],
            ],
            [
                'name_en' => 'Leadership Workshop',
                'name_ar' => 'ورشة عمل القيادة',
                'description_en' => 'An intensive leadership workshop for executives focusing on decision-making, team performance, and organizational culture.',
                'description_ar' => 'ورشة قيادة مكثفة للمدراء التنفيذيين تركز على اتخاذ القرار وأداء الفرق وثقافة المؤسسة.',
                'slug' => 'leadership-workshop',
                'event_type' => 'workshop',
                'registration_mode' => 'free_registration',
                'tier' => 'corporate',
                'categories' => [
                    ['name' => 'Normal', 'slug' => 'normal', 'capacity' => 100],
                    ['name' => 'VIP', 'slug' => 'vip', 'capacity' => 20],
                ],
                'venues' => [
                    [
                        'name_en' => 'Dubai Knowledge Park Auditorium',
                        'name_ar' => 'قاعة مجمع دبي للمعرفة',
                        'city' => $dubai,
                        'location_address' => 'Block 10, Knowledge Village, Dubai, United Arab Emirates',
                        'latitude' => 25.1022000,
                        'longitude' => 55.1645000,
                        'start_offset_days' => 45,
                        'end_offset_days' => 45,
                        'reg_open_offset_days' => 5,
                        'reg_close_offset_days' => 44,
                    ],
                ],
            ],
            [
                'name_en' => 'Product Launch Seminar',
                'name_ar' => 'ندوة إطلاق المنتج',
                'description_en' => 'Join us for the official product launch seminar featuring live demos, partner showcases, and exclusive early-access offers.',
                'description_ar' => 'انضم إلينا في ندوة الإطلاق الرسمية للمنتج مع عروض حية وعروض الشركاء وعروض حصرية للوصول المبكر.',
                'slug' => 'product-launch',
                'event_type' => 'seminar',
                'registration_mode' => 'paid_ticketing',
                'tier' => 'public',
                'categories' => [
                    ['name' => 'Normal', 'slug' => 'normal', 'capacity' => 200],
                    ['name' => 'VIP', 'slug' => 'vip', 'capacity' => 30],
                ],
                'venues' => [
                    [
                        'name_en' => 'Cairo Festival City Ballroom',
                        'name_ar' => 'قاعة مدينة القاهرة للمهرجانات',
                        'city' => $cairo,
                        'location_address' => 'Ring Road, New Cairo, Cairo Governorate, Egypt',
                        'latitude' => 30.0284000,
                        'longitude' => 31.4085000,
                        'start_offset_days' => 60,
                        'end_offset_days' => 60,
                        'reg_open_offset_days' => 10,
                        'reg_close_offset_days' => 59,
                    ],
                    [
                        'name_en' => 'Jeddah Hilton Conference Wing',
                        'name_ar' => 'جناح مؤتمرات هيلتون جدة',
                        'city' => $jeddah,
                        'location_address' => 'North Corniche Road, Jeddah 21411, Saudi Arabia',
                        'latitude' => 21.5433000,
                        'longitude' => 39.1728000,
                        'start_offset_days' => 67,
                        'end_offset_days' => 67,
                        'reg_open_offset_days' => 10,
                        'reg_close_offset_days' => 66,
                    ],
                ],
            ],
        ];

        foreach ($events as $eventData) {
            $venueSchedules = collect($eventData['venues'])->map(fn (array $venue): array => [
                'start_at' => now()->addDays($venue['start_offset_days'])->startOfDay()->addHours(9),
                'end_at' => now()->addDays($venue['end_offset_days'])->startOfDay()->addHours(18),
                'registration_opens_at' => now()->addDays($venue['reg_open_offset_days'])->startOfDay(),
                'registration_closes_at' => now()->addDays($venue['reg_close_offset_days'])->endOfDay(),
            ]);

            $event = Event::query()->withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $eventData['slug']],
                [
                    'name_en' => $eventData['name_en'],
                    'name_ar' => $eventData['name_ar'],
                    'description_en' => $eventData['description_en'],
                    'description_ar' => $eventData['description_ar'],
                    'event_type' => $eventData['event_type'],
                    'registration_mode' => $eventData['registration_mode'],
                    'tier' => $eventData['tier'],
                    'status' => 'draft',
                    'timezone' => $tenant->timezone,
                    'created_by_user_id' => User::query()->first()?->id,
                ],
            );

            $keepVenueIds = [];

            foreach ($eventData['venues'] as $i => $venue) {
                /** @var City|null $city */
                $city = $venue['city'];
                $schedule = $venueSchedules[$i];

                $eventVenue = EventVenue::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'event_id' => $event->id,
                        'name_en' => $venue['name_en'],
                    ],
                    [
                        'name_ar' => $venue['name_ar'],
                        'country_id' => $city?->country_id,
                        'city_id' => $city?->id,
                        'location_address' => $venue['location_address'],
                        'latitude' => $venue['latitude'],
                        'longitude' => $venue['longitude'],
                        'start_at' => $schedule['start_at'],
                        'end_at' => $schedule['end_at'],
                        'registration_opens_at' => $schedule['registration_opens_at'],
                        'registration_closes_at' => $schedule['registration_closes_at'],
                        'sort_order' => $i,
                    ],
                );

                $keepVenueIds[] = $eventVenue->id;
            }

            EventVenue::query()
                ->where('tenant_id', $tenant->id)
                ->where('event_id', $event->id)
                ->whereNotIn('id', $keepVenueIds)
                ->delete();

            foreach ($eventData['categories'] as $i => $cat) {
                $template = CategoryTemplate::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('slug', $cat['slug'])
                    ->first();

                $eventCategory = EventCategory::query()->updateOrCreate(
                    ['event_id' => $event->id, 'slug' => $cat['slug']],
                    [
                        'category_template_id' => $template?->id,
                        'name' => $cat['name'],
                        'name_ar' => $template?->name_ar ?? $cat['name'],
                        'color' => $template?->color,
                        'capacity' => $cat['capacity'],
                        'sort_order' => $i,
                    ],
                );

                if ($template) {
                    $eventCategory->privileges()->delete();
                    foreach ($template->privileges as $priv) {
                        $eventCategory->privileges()->create([
                            'key' => $priv->key,
                            'label' => $priv->label,
                            'label_ar' => $priv->label_ar,
                            'effect' => $priv->effect,
                            'target_type' => $priv->target_type,
                            'target_id' => $priv->target_id,
                        ]);
                    }
                }
            }
        }
    }

    private function city(string $countryCode, string $cityNameEn): ?City
    {
        $country = Country::query()->where('code', $countryCode)->first();

        if (! $country) {
            return null;
        }

        return City::query()
            ->where('country_id', $country->id)
            ->where('name_en', $cityNameEn)
            ->first();
    }
}
