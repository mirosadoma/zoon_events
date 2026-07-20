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
        // Prefer Professional so demo unlocks kiosk/ACS/wallet features; fall back to trial.
        $plan = SubscriptionPlan::query()->where('name', 'Professional')->first()
            ?? SubscriptionPlan::query()->where('is_trial', true)->first();
        $tenant = Tenant::query()->where('slug', DemoAccounts::TENANT_SLUG)->first();

        if ($tenant && $plan) {
            TenantSubscription::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => now()->addDays((int) ($plan->duration_days ?: 90)),
                    'amount_paid' => 0,
                ],
            );
        }
    }
}
