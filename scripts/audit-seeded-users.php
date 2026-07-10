<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Modules\Authorization\Application\PermissionEvaluator;
use App\Modules\Tenancy\Domain\Context\TenantContext;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Database\Seeders\DemoAccounts;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$evaluator = app(PermissionEvaluator::class);
$definitions = PermissionSeeder::definitions();

echo "=== Seeded user credentials & permission audit ===\n\n";

$credentials = [
    DemoAccounts::PRIMARY_DEMO_EMAIL => DemoAccounts::PRIMARY_DEMO_PASSWORD,
    DemoAccounts::ONSITE_EMAIL => DemoAccounts::ONSITE_PASSWORD,
    DemoAccounts::ACS_EMAIL => DemoAccounts::ACS_PASSWORD,
    DemoAccounts::TICKETING_EMAIL => DemoAccounts::TICKETING_PASSWORD,
    DemoAccounts::PLATFORM_ADMIN_EMAIL => config('zonetec.bootstrap_admin_password', 'admin1234'),
    DemoAccounts::FIXTURE_CREATOR_EMAIL => DemoAccounts::FIXTURE_CREATOR_PASSWORD,
    DemoAccounts::FIXTURE_ALPHA_EMAIL => DemoAccounts::FIXTURE_ALPHA_PASSWORD,
    DemoAccounts::FIXTURE_BRAVO_EMAIL => DemoAccounts::FIXTURE_BRAVO_PASSWORD,
];

foreach (User::query()->orderBy('email')->get() as $user) {
    $password = $credentials[$user->email] ?? '(unknown — check .env or seeder)';
    echo "User: {$user->name}\n";
    echo "  Email:    {$user->email}\n";
    echo "  Password: {$password}\n";

    $platformRoles = DB::table('platform_role_assignments as a')
        ->join('platform_roles as r', 'r.id', '=', 'a.platform_role_id')
        ->where('a.user_id', $user->id)
        ->whereNull('a.revoked_at')
        ->pluck('r.name');

    echo '  Platform roles: '.($platformRoles->isEmpty() ? 'NONE' : $platformRoles->implode(', '))."\n";

    $memberships = TenantMembership::query()->with('tenant')->where('user_id', $user->id)->get();

    if ($memberships->isEmpty()) {
        echo "  Tenant memberships: NONE\n";
    }

    foreach ($memberships as $membership) {
        $tenantRoles = DB::table('tenant_role_assignments as a')
            ->join('tenant_roles as r', 'r.id', '=', 'a.tenant_role_id')
            ->where('a.tenant_membership_id', $membership->id)
            ->whereNull('a.revoked_at')
            ->pluck('r.name');

        echo "  Tenant {$membership->tenant->slug}: roles=".($tenantRoles->isEmpty() ? 'NONE ⚠' : $tenantRoles->implode(', '))."\n";

        if ($tenantRoles->isEmpty()) {
            continue;
        }

        $context = new TenantContext($membership->tenant, $membership, $user);
        $tenantPerms = collect($definitions)->where('scope', 'tenant');
        $granted = $tenantPerms->filter(fn (array $d): bool => $evaluator->hasTenantPermission($context, $d['key']))->pluck('key');
        echo "    Effective tenant permissions: {$granted->count()} / {$tenantPerms->count()}\n";
    }

    $platformPerms = collect($definitions)->where('scope', 'platform');
    $grantedPlatform = $platformPerms->filter(fn (array $d): bool => $evaluator->hasPlatformPermission($user, $d['key']))->pluck('key');
    echo "  Effective platform permissions: {$grantedPlatform->count()} / {$platformPerms->count()}\n";
    echo "\n";
}

echo "=== FoundationSeeder role definitions (expected) ===\n\n";
echo "Platform roles: Platform Administrator, Security Auditor, Operations Viewer\n";
echo "Tenant roles (per tenant): Tenant Administrator, Event Manager, Ticketing Manager, On-Site Staff, ACS Operator\n";
echo "\nRun after `php artisan db:seed` to verify assignments.\n";
