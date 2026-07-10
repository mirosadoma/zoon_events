<?php

namespace App\Modules\Identity\Application;

use App\Exceptions\FoundationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class AuthenticateUser
{
    public function attempt(string $email, string $password): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            Hash::check($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            throw FoundationException::unauthenticated('The provided credentials are invalid.');
        }

        if (! $user->isActive()) {
            throw FoundationException::forbidden('user_inactive', 'The user account is inactive.');
        }

        return $user;
    }
}
