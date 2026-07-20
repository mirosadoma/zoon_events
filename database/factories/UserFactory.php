<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Shared\Domain\LifecycleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => LifecycleStatus::Active->value,
            'preferred_locale' => 'en',
        ];
    }
}
