<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff front door is separate from the customers' one.
 *
 * Three admin-area tests already assert the redirect incidentally; this states
 * the intent in one place, and covers the half they cannot — that signing in at
 * the staff door with a customer account is refused rather than quietly allowed.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff@retab.test',
            'password' => bcrypt('secret-password'),
            'role' => 'admin',
        ]);
    }

    private function customer(): User
    {
        return User::forceCreate([
            'name' => 'Shopper',
            'email' => 'shopper@retab.test',
            'password' => bcrypt('secret-password'),
            'role' => 'customer',
        ]);
    }

    public function test_the_staff_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    /**
     * 🔑 The redirect is what makes the split real. Without it a staff member
     * whose session expired lands on the CUSTOMER login, which offers Google,
     * WhatsApp and a sign-up link — none of which can produce a staff account.
     */
    public function test_a_guest_reaching_the_admin_area_is_sent_to_the_staff_door(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    }

    /** And the storefront still sends guests to the customers' door. */
    public function test_a_guest_reaching_a_customer_page_is_sent_to_the_customer_door(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_staff_can_sign_in_at_the_staff_door(): void
    {
        $this->post('/admin/login', [
            'email' => $this->staff()->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    /**
     * 🔴 A customer must not end up holding a session created from the staff
     * door, and must be told WHICH thing went wrong — "wrong page" and "wrong
     * password" send someone hunting in completely different places.
     */
    public function test_a_customer_is_refused_at_the_staff_door_and_left_signed_out(): void
    {
        $this->post('/admin/login', [
            'email' => $this->customer()->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** The customer door still works for staff, so a broken /admin/login is not a lockout. */
    public function test_staff_can_still_sign_in_at_the_customer_door(): void
    {
        $this->post('/login', [
            'email' => $this->staff()->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertAuthenticated();
    }
}
