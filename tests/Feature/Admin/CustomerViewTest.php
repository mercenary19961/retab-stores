<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerViewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('secret'), 'role' => 'admin']);
    }

    public function test_admin_sees_customers_but_not_staff(): void
    {
        $admin = $this->admin();
        User::create(['name' => 'Zaid', 'email' => 'z@test.com', 'password' => bcrypt('secret'), 'confirmed_purchases_count' => 3]);

        $response = $this->actingAs($admin)->get('/admin/customers');

        $response->assertOk();
        $page = $response->inertiaPage();
        $names = array_column($page['props']['customers']['data'], 'name');
        $this->assertContains('Zaid', $names);
        $this->assertNotContains('Admin', $names);
    }

    public function test_customer_detail_shows_loyalty(): void
    {
        $customer = User::create(['name' => 'Zaid', 'email' => 'z@test.com', 'password' => bcrypt('secret'), 'confirmed_purchases_count' => 7]);

        $response = $this->actingAs($this->admin())->get("/admin/customers/{$customer->id}");

        $response->assertOk();
        $loyalty = $response->inertiaPage()['props']['loyalty'];
        $this->assertSame(7, $loyalty['confirmed_purchases']);
        $this->assertSame(2, $loyalty['progress']); // 7 % 5
    }

    public function test_staff_detail_is_hidden_and_customers_forbidden(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get("/admin/customers/{$admin->id}")->assertNotFound();

        $customer = User::create(['name' => 'C', 'email' => 'c@test.com', 'password' => bcrypt('secret')]);
        $this->actingAs($customer)->get('/admin/customers')->assertForbidden();
    }
}
