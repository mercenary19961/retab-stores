<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_customers_are_redirected_from_legacy_dashboard_to_account()
    {
        $this->actingAs(User::factory()->create()); // customer

        $this->get('/dashboard')->assertRedirect(route('account.dashboard'));
    }

    public function test_staff_reach_the_admin_dashboard()
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/dashboard')->assertOk();
    }
}
