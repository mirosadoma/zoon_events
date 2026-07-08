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

        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('FoundationDashboard')->where('scope', 'platform'));
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
