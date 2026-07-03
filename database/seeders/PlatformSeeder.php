<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\Permission;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use App\Modules\Shared\Domain\LifecycleStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('PlatformSeeder is disabled in production. Use the explicit bootstrap administrator command.');
        }

        $email = (string) config('zonetec.bootstrap_admin_email');
        $password = (string) config('zonetec.bootstrap_admin_password');

        if ($email === '' || strlen($password) < 6) {
            throw new \RuntimeException('Explicit bootstrap administrator email and a password of at least 6 characters are required.');
        }

        DB::transaction(function (): void {
            $email = (string) config('zonetec.bootstrap_admin_email');
            $password = (string) config('zonetec.bootstrap_admin_password');

            $bootstrapUser = User::query()->firstOrCreate(
                ['email' => mb_strtolower($email)],
                [
                    'name' => 'Platform Administrator',
                    'password' => Hash::make($password),
                    'status' => LifecycleStatus::Active->value,
                    'preferred_locale' => 'en',
                ],
            );

            $platformRole = PlatformRole::query()->firstOrCreate(
                ['name' => 'Platform Administrator'],
                [
                    'description' => 'Full privileged foundation administration.',
                    'is_system' => true,
                    'created_by_user_id' => $bootstrapUser->id,
                ],
            );

            DB::table('platform_role_permissions')->where('platform_role_id', $platformRole->id)->delete();
            foreach (Permission::query()->where('scope', 'platform')->pluck('id')->all() as $permissionId) {
                DB::table('platform_role_permissions')->insert([
                    'platform_role_id' => $platformRole->id,
                    'permission_id' => $permissionId,
                    'granted_by_user_id' => $bootstrapUser->id,
                    'created_at' => now(),
                ]);
            }

            DB::table('platform_role_assignments')->updateOrInsert(
                ['user_id' => $bootstrapUser->id, 'platform_role_id' => $platformRole->id, 'revoked_at' => null],
                [
                    'id' => (string) Str::ulid(),
                    'granted_by_user_id' => $bootstrapUser->id,
                    'expires_at' => null,
                    'revoked_by_user_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });
    }
}
