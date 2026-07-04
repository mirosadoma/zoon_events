<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use Illuminate\Database\Seeder;
use LogicException;

final class Phase1RegistrationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Synthetic Phase 1 registration data cannot be seeded in production.');
        }

        $tenant = Tenant::query()->first();
        $actor = User::query()->first();
        if ($tenant === null || $actor === null) {
            return;
        }

        Event::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'phase-1-synthetic'],
            [
                'name_en' => 'Phase 1 Synthetic Event', 'name_ar' => 'فعالية المرحلة الأولى',
                'tier' => 'public', 'status' => 'draft', 'timezone' => 'Africa/Cairo',
                'start_at' => now()->addMonth(), 'end_at' => now()->addMonth()->addHours(4),
                'registration_opens_at' => now(), 'registration_closes_at' => now()->addMonth()->subHour(),
                'capacity' => 100, 'created_by_user_id' => $actor->id,
            ],
        );
    }
}
