<?php

namespace Tests\Support;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;

trait ActsAsFoundationUser
{
    protected function actingAsFoundationUser(array $attributes = []): AuthenticatableUser
    {
        $user = new class extends AuthenticatableUser {};

        $user->forceFill(array_merge([
            'id' => '01JZ4USER00000000000000000',
            'name' => 'Foundation User',
            'email' => 'foundation.user@example.test',
            'password' => bcrypt('foundation-password'),
        ], $attributes));

        $this->actingAs($user);

        return $user;
    }
}
