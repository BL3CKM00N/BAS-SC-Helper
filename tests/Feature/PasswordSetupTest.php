<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ temporary password

    public function test_someone_with_a_temporary_password_must_choose_their_own_first(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        foreach (['/dashboard', '/reminders', '/teams', '/account/profile'] as $page) {
            $this->actingAs($user)->get($page)->assertRedirect(route('password.change'));
        }

        $this->actingAs($user)->get('/password/change')->assertOk()->assertSee('Choose your password');
    }

    public function test_choosing_a_password_unlocks_the_app(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->post('/password/change', ['password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('a-brand-new-password', $user->password));
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_a_weak_or_mismatched_password_is_refused(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post('/password/change', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($user)->post('/password/change', ['password' => 'long-enough-password', 'password_confirmation' => 'different-password'])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_normal_users_are_not_sent_to_the_password_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    }

    public function test_signing_out_still_works_before_choosing_a_password(): void
    {
        $this->actingAs(User::factory()->create(['must_change_password' => true]))->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guests_cannot_open_the_password_page(): void
    {
        $this->get('/password/change')->assertRedirect('/');
    }

    // ------------------------------------------------------------ the invitation link

    private function invited(): User
    {
        return User::factory()->create(['invited_at' => now(), 'password' => 'random-unknown-password']);
    }

    public function test_the_invitation_link_shows_the_password_form(): void
    {
        $user = $this->invited();
        $url = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['user' => $user->id]);

        $this->get($url)->assertOk()->assertSee('Welcome, '.$user->name)->assertSee($user->email)->assertSee('action="'.e($url).'"', false);
    }

    public function test_the_invitation_sets_the_password_and_signs_the_person_in(): void
    {
        $user = $this->invited();
        $url = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['user' => $user->id]);

        $this->post($url, ['password' => 'my-own-password-1', 'password_confirmation' => 'my-own-password-1'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNull($user->invited_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('my-own-password-1', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_invitation_can_only_be_used_once(): void
    {
        $user = $this->invited();
        $url = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['user' => $user->id]);
        $this->post($url, ['password' => 'my-own-password-1', 'password_confirmation' => 'my-own-password-1']);
        Auth::logout();

        $this->get($url)->assertForbidden();
        $this->post($url, ['password' => 'someone-elses-password', 'password_confirmation' => 'someone-elses-password'])->assertForbidden();

        $this->assertTrue(Hash::check('my-own-password-1', $user->fresh()->password));
    }

    public function test_unsigned_tampered_and_expired_invitations_are_refused(): void
    {
        $user = $this->invited();
        $other = $this->invited();
        $signed = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['user' => $user->id]);

        $this->get(route('invite.show', $user))->assertForbidden();
        $this->get(str_replace('/invite/'.$user->id, '/invite/'.$other->id, $signed))->assertForbidden();
        $this->get(URL::temporarySignedRoute('invite.show', now()->subMinute(), ['user' => $user->id]))->assertForbidden();
    }

    public function test_an_account_that_was_never_invited_has_no_invitation_link(): void
    {
        $user = User::factory()->create(['invited_at' => null]);

        $this->get(URL::temporarySignedRoute('invite.show', now()->addDay(), ['user' => $user->id]))->assertForbidden();
    }

    public function test_a_weak_password_is_refused_on_the_invitation(): void
    {
        $user = $this->invited();
        $url = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['user' => $user->id]);

        $this->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

        $this->assertNotNull($user->fresh()->invited_at);
        $this->assertGuest();
    }
}
