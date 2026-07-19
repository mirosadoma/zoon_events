<?php

namespace Database\Seeders;

use App\Modules\Subscriptions\Infrastructure\Persistence\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Infrastructure\Persistence\Models\TenantSubscription;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Seeder;

final class SubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $trialPlan = SubscriptionPlan::query()->where('is_trial', true)->first();
        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->first();

        if ($tenant && $trialPlan) {
            TenantSubscription::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'plan_id' => $trialPlan->id],
                ['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($trialPlan->duration_days), 'amount_paid' => 0],
            );
        }
    }
}
