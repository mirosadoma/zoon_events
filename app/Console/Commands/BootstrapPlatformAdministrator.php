<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class BootstrapPlatformAdministrator extends Command
{
    protected $signature = 'zonetec:bootstrap-admin {--email=} {--password=}';

    protected $description = 'Create or recover the first platform administrator using explicit credentials.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->option('email') ?: config('zonetec.bootstrap_admin_email'))));
        $password = (string) ($this->option('password') ?: config('zonetec.bootstrap_admin_password'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16 || $this->isKnownPassword($password)) {
            $this->components->error('Explicit valid email and a non-default password of at least 16 characters are required.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $password): void {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => 'Platform Administrator', 'password' => Hash::make($password), 'status' => 'active', 'preferred_locale' => 'en'],
            );
            $role = PlatformRole::query()->where('name', 'Platform Administrator')->where('is_system', true)->first();

            if (! $role instanceof PlatformRole) {
                throw new RuntimeException('Run the permission and system-role seeders before bootstrap.');
            }

            DB::table('platform_role_assignments')->updateOrInsert(
                ['user_id' => $user->id, 'platform_role_id' => $role->id, 'revoked_at' => null],
                ['id' => (string) Str::ulid(), 'granted_by_user_id' => $user->id, 'expires_at' => null, 'revoked_by_user_id' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        });

        $this->components->info('Platform administrator bootstrapped successfully.');

        return self::SUCCESS;
    }

    private function isKnownPassword(string $password): bool
    {
        return in_array(mb_strtolower($password), [
            'password',
            'changeme',
            'changethisduringbootstrap!2026',
            'platform-administrator',
        ], true);
    }
}
