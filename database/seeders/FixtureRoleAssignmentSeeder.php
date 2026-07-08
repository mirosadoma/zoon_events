<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\TenantRole;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\Tenant;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Assigns system tenant roles to synthetic fixture users after SystemRoleSeeder
 * has created roles for every tenant.
 */
final class FixtureRoleAssignmentSeeder extends Seeder
{
    /** @var array<string, array{tenant_slug: string, role: string}> */
    private const ASSIGNMENTS = [
        'fixture.alpha@example.test' => ['tenant_slug' => 'fixture-alpha', 'role' => 'Tenant Administrator'],
        'fixture.bravo@example.test' => ['tenant_slug' => 'fixture-bravo', 'role' => 'Event Manager'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $grantor = User::query()->where('email', 'fixture.creator@example.test')->first()
            ?? User::query()->orderBy('created_at')->first();

        if (! $grantor instanceof User) {
            return;
        }

        foreach (self::ASSIGNMENTS as $email => $config) {
            $user = User::query()->where('email', $email)->first();
            $tenant = Tenant::query()->where('slug', $config['tenant_slug'])->first();

            if (! $user instanceof User || ! $tenant instanceof Tenant) {
                continue;
            }

            $membership = TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->first();

            $role = TenantRole::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('name', $config['role'])
                ->first();

            if (! $membership instanceof TenantMembership || ! $role instanceof TenantRole) {
                continue;
            }

            DB::table('tenant_role_assignments')->updateOrInsert(
                [
                    'tenant_id' => $tenant->id,
                    'tenant_membership_id' => $membership->id,
                    'tenant_role_id' => $role->id,
                    'revoked_at' => null,
                ],
                [
                    'granted_by_user_id' => $grantor->id,
                    'expires_at' => null,
                    'revoked_by_user_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
