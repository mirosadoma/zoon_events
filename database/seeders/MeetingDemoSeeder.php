<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** @deprecated Use `php artisan db:seed` — kept for artisan class name compatibility. */
final class MeetingDemoSeeder extends Seeder
{
    public const DEMO_EMAIL = DemoAccounts::PRIMARY_DEMO_EMAIL;

    public const DEMO_PASSWORD = DemoAccounts::PRIMARY_DEMO_PASSWORD;

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
    }
}
