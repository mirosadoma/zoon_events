<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_active_user_can_login_and_logout(): void
    {
        $password = 'Synthetic-Password-123!';
        $user = User::factory()->create(['password' => $password]);

        $this->get('/en/dashboard')->assertRedirect('/en/login');
        $this->post('/en/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/en/dashboard');
        $this->get('/en/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FoundationDashboard')->where('scope', 'platform'));
        $this->post('/en/logout')->assertRedirect('/en/login');
        $this->get('/en/dashboard')->assertRedirect('/en/login');
    }

    public function test_invalid_login_returns_validation_errors_instead_of_server_error(): void
    {
        $user = User::factory()->create(['password' => 'Synthetic-Password-123!']);

        $this->from('/en/login')
            ->post('/en/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/en/login')
            ->assertSessionHasErrors(['email']);
    }
}
