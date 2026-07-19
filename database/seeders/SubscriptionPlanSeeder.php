<?php

namespace Database\Seeders;

use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

final class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free Trial',
                'name_ar' => 'تجربة مجانية',
                'description' => '14-day free trial with limited features.',
                'description_ar' => 'تجربة مجانية لمدة 14 يوم مع ميزات محدودة.',
                'is_trial' => true,
                'duration_days' => 14,
                'price' => 0,
                'currency' => 'SAR',
                'max_events' => 2,
                'max_attendees' => 50,
                'max_devices' => 2,
                'allowed_features' => ['registration', 'ticketing', 'check_in'],
                'sort_order' => 0,
            ],
            [
                'name' => 'Starter',
                'name_ar' => 'المبتدئ',
                'description' => '30-day starter plan for small events.',
                'description_ar' => 'خطة المبتدئ لمدة 30 يوم للفعاليات الصغيرة.',
                'is_trial' => false,
                'duration_days' => 30,
                'price' => 500,
                'currency' => 'SAR',
                'max_events' => 5,
                'max_attendees' => 200,
                'max_devices' => 5,
                'allowed_features' => ['registration', 'ticketing', 'check_in', 'badge_printing', 'kiosk'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'name_ar' => 'الاحترافي',
                'description' => '90-day professional plan for medium events.',
                'description_ar' => 'خطة احترافية لمدة 90 يوم للفعاليات المتوسطة.',
                'is_trial' => false,
                'duration_days' => 90,
                'price' => 2000,
                'currency' => 'SAR',
                'max_events' => 20,
                'max_attendees' => 1000,
                'max_devices' => 15,
                'allowed_features' => ['registration', 'ticketing', 'check_in', 'badge_printing', 'kiosk', 'acs', 'identity_verification', 'wallet_passes'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'name_ar' => 'المؤسسات',
                'description' => '365-day enterprise plan with unlimited features.',
                'description_ar' => 'خطة المؤسسات لمدة عام كامل بدون حدود.',
                'is_trial' => false,
                'duration_days' => 365,
                'price' => 10000,
                'currency' => 'SAR',
                'max_events' => null,
                'max_attendees' => null,
                'max_devices' => null,
                'allowed_features' => null,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );
        }
    }
}
