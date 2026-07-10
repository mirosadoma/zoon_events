<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TimezoneSeeder::class);
        $this->call(GeographySeeder::class);
        $this->call(FoundationSeeder::class);
        $this->call(DemoContentSeeder::class);
    }
}
