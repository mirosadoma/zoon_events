<?php

namespace Tests\Feature\AdminConsole;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('admin-dashboard')]
class OverviewAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_home_but_not_dashboard_or_profile(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard_and_profile(): void
    {
        $password = 'Synthetic-Password-123!';
        $user = User::factory()->create(['password' => $password]);

        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('FoundationDashboard')
                ->has('overview'));

        $this->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile')
                ->has('profile'));
    }
}
