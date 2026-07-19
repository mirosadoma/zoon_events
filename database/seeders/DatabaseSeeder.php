<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Foundation: permissions, platform roles, tenants, users, tenant roles
            FoundationSeeder::class,

            // 2. Geography & Timezones
            TimezoneSeeder::class,
            GeographySeeder::class,

            // 3. Subscription plans
            SubscriptionPlanSeeder::class,

            // 4. Demo subscriptions for tenants
            SubscriptionDemoSeeder::class,

            // 5. Events + Categories + Privileges
            EventDemoSeeder::class,

            // 6. Builder demos: badge templates + registration forms + branding
            BuilderDemoSeeder::class,

            // 7. Operational examples: attendees, credentials, kiosk, ACS, scans, badges
            DemoContentSeeder::class,
        ]);
    }
}
