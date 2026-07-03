<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class FoundationIsolationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Synthetic isolation fixtures cannot be seeded in production.');
        }

        DB::transaction(function (): void {
            $creator = User::query()->updateOrCreate(
                ['email' => 'fixture.creator@example.test'],
                ['name' => 'Fixture Creator', 'password' => Hash::make('synthetic-only-creator-password'), 'status' => 'active', 'preferred_locale' => 'en'],
            );

            foreach (['alpha', 'bravo'] as $name) {
                $user = User::query()->updateOrCreate(
                    ['email' => "fixture.{$name}@example.test"],
                    ['name' => "Fixture {$name}", 'password' => Hash::make("synthetic-only-{$name}-password"), 'status' => 'active', 'preferred_locale' => 'en'],
                );
                $tenant = Tenant::query()->updateOrCreate(
                    ['slug' => "fixture-{$name}"],
                    ['name' => "Fixture {$name}", 'status' => 'active', 'default_locale' => 'en', 'timezone' => 'UTC', 'data_residency_region' => 'test', 'created_by_user_id' => $creator->id],
                );
                TenantMembership::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    ['status' => 'active', 'created_by_user_id' => $creator->id],
                );
            }
        });
    }
}
