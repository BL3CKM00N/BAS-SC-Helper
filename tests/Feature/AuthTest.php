<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_login_card_on_the_home_page(): void
    {
        $response = $this->get('/')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('name="password"', false);

        // The card shows the logo and the form only, no app name.
        preg_match('/<main.*<\/main>/s', $response->getContent(), $card);
        $this->assertStringNotContainsString('BAS SC Helper', $card[0]);
    }

    public function test_guests_are_sent_to_the_login_card(): void
    {
        $this->get('/dashboard')->assertRedirect('/');
        $this->get('/admin')->assertRedirect('/');
        $this->get('/admin/users')->assertRedirect('/');
    }

    public function test_health_check_and_service_worker_stay_public(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/sw.js')->assertOk();
    }

    public function test_a_user_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->from('/')
            ->post('/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_in_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_card(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_a_normal_user_gets_no_admin_tab_and_no_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('href="/admin"', false);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_an_admin_sees_the_admin_tab_and_can_open_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('href="/admin"', false);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('App dashboard')
            ->assertSee('href="/dashboard"', false);
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($admin->email);
        $this->assertSame(Role::ADMIN, $admin->role->name);
    }
}
