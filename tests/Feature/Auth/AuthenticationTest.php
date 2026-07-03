<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_receives_plaintext_token_once_and_can_revoke_it(): void
    {
        $password = 'Synthetic-Password-123!';
        $user = User::factory()->create(['password' => $password]);

        $response = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => $password, 'device_name' => 'test']);
        $response->assertOk()->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
        self::assertSame(1, $user->tokens()->count());

        $this->withToken($response->json('data.token'))->deleteJson('/api/v1/auth/token/current')->assertNoContent();
        self::assertSame(0, $user->tokens()->count());
    }

    public function test_invalid_and_inactive_users_fail_safely_and_excluded_routes_are_absent(): void
    {
        RateLimiter::clear('auth');
        $password = 'Synthetic-Password-123!';
        $inactive = User::factory()->create(['password' => $password, 'status' => 'suspended', 'suspended_at' => now()]);

        $this->postJson('/api/v1/auth/token', ['email' => 'unknown@example.test', 'password' => 'wrong', 'device_name' => 'test'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', ['email' => $inactive->email, 'password' => $password, 'device_name' => 'test'])->assertForbidden();
        $this->postJson('/register', [])->assertNotFound();
        $this->postJson('/forgot-password', [])->assertNotFound();
        $this->postJson('/two-factor-challenge', [])->assertNotFound();
    }
}
