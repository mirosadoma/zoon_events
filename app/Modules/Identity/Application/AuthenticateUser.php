<?php

namespace App\Modules\Identity\Application;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Authorization\Infrastructure\Persistence\Models\PlatformRoleAssignment;
use App\Modules\Shared\Domain\LifecycleStatus;
use App\Modules\Tenancy\Infrastructure\Persistence\Models\TenantMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

final class AuthenticateUser
{
    public function attempt(string $email, string $password): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            Hash::check($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            throw FoundationException::unauthenticated('These credentials do not match our records.');
        }

        if (! $user->isActive()) {
            throw FoundationException::forbidden('user_inactive', 'The user account is inactive.');
        }

        if (! $this->userHasLoginAccess($user)) {
            throw FoundationException::forbidden(
                'tenant_inactive',
                'Your organization is disabled. Contact the platform administrator.',
            );
        }

        return $user;
    }

    private function userHasLoginAccess(User $user): bool
    {
        $hasPlatformRole = PlatformRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->exists();

        if ($hasPlatformRole) {
            return true;
        }

        $hasMembership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->exists();

        if (! $hasMembership) {
            return true;
        }

        return TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('status', LifecycleStatus::Active)
            ->whereHas('tenant', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('status', LifecycleStatus::Active);
            })
            ->exists();
    }
}
