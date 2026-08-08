<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/password');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/password');
    }

    /**
     * The admin panel reuses this same route for its own-account password section
     * and reports the result through the toast layer, which reads flash.success.
     * Without the flash the admin form would succeed silently.
     */
    public function test_a_success_flash_is_set_for_the_admin_toast_layer()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->from('/admin/users')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/admin/users') // back() lands the admin form where it submitted
            ->assertSessionHas('success', __('messages.profile.password_updated'));
    }

    /**
     * `current_password` makes this endpoint a credential-guessing oracle for
     * anyone holding a session, so it is throttled (10/min).
     */
    public function test_password_updates_are_rate_limited()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
        }

        $this->actingAs($user)
            ->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertStatus(429);
    }

    /**
     * Regression guard for the shared-bucket bug: an unnamed `throttle:10,1` keys
     * on sha1(domain|IP) with no route URI, so it would share one counter with
     * every other rate-limited route. Spending this endpoint's budget must not
     * lock the same visitor out of logging in.
     */
    public function test_password_updates_do_not_consume_the_login_rate_limit()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->put('/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
        }

        $this->assertNotSame(
            429,
            $this->post('/login', ['email' => 'someone@example.com', 'password' => 'x'])->status(),
            'password-update attempts must not consume the login rate limit',
        );
    }
}
