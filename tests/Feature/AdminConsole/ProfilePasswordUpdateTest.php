<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
class ProfilePasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_staff_can_update_phone(): void
    {
        $user = User::factory()->create([
            'type' => 'staff',
            'phone' => null,
            'preferred_locale' => 'en',
        ]);

        $this->actingAs($user)
            ->from('/en/profile')
            ->patch('/en/profile', [
                'name' => $user->name,
                'phone' => '+966501234567',
                'preferred_locale' => 'en',
            ])
            ->assertRedirect('/en/profile')
            ->assertSessionHas('status', 'profile-updated');

        self::assertSame('+966501234567', $user->fresh()->phone);
    }

    public function test_authenticated_staff_can_update_password(): void
    {
        $current = 'Synthetic-Password-123!';
        $user = User::factory()->create([
            'type' => 'staff',
            'password' => Hash::make($current),
        ]);

        $this->actingAs($user)
            ->from('/en/profile')
            ->put('/en/profile/password', [
                'current_password' => $current,
                'password' => 'New-Synthetic-Password-456!',
                'password_confirmation' => 'New-Synthetic-Password-456!',
            ])
            ->assertRedirect('/en/profile')
            ->assertSessionHas('status', 'profile-password-updated');

        self::assertTrue(Hash::check('New-Synthetic-Password-456!', $user->fresh()->password));
    }

    public function test_wrong_current_password_returns_validation_error(): void
    {
        $user = User::factory()->create([
            'type' => 'staff',
            'password' => Hash::make('Synthetic-Password-123!'),
        ]);

        $this->actingAs($user)
            ->putJson('/en/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Synthetic-Password-456!',
                'password_confirmation' => 'New-Synthetic-Password-456!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        self::assertTrue(Hash::check('Synthetic-Password-123!', $user->fresh()->password));
    }

    public function test_visitor_cannot_update_admin_profile_password(): void
    {
        $user = User::factory()->create([
            'type' => 'visitor',
            'password' => Hash::make('Synthetic-Password-123!'),
        ]);

        $this->actingAs($user)
            ->put('/en/profile/password', [
                'current_password' => 'Synthetic-Password-123!',
                'password' => 'New-Synthetic-Password-456!',
                'password_confirmation' => 'New-Synthetic-Password-456!',
            ])
            ->assertRedirect('/en/visitor');

        self::assertTrue(Hash::check('Synthetic-Password-123!', $user->fresh()->password));
    }
}
