<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Account\UserSessions;
use App\Support\DeviceInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const MAC_CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /** A real session id is 40 letters and digits, anything else is thrown away by Laravel. */
    private const CURRENT = 'currentsessionidcurrentsessionid00000000';

    private const CURRENT_TWO = 'secondsessionidsecondsessionid0000000000';

    private const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1';

    private function addSession(User $user, string $id, array $attributes = []): string
    {
        DB::table('sessions')->updateOrInsert(['id' => $id], $attributes + [
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => self::MAC_CHROME,
            'payload' => '',
            'last_activity' => now()->subHour()->timestamp,
        ]);

        return $id;
    }

    /**
     * Act as the user with a known session id, so "this device" can be recognised.
     */
    private function signedIn(User $user, string $currentId = self::CURRENT): static
    {
        $this->addSession($user, $currentId, ['last_activity' => now()->timestamp]);

        return $this->actingAs($user)->withCookie(config('session.cookie'), $currentId);
    }

    private function handle(string $sessionId): string
    {
        return app(UserSessions::class)->handle($sessionId);
    }

    // ------------------------------------------------------------ reading a user agent

    public function test_it_describes_common_browsers(): void
    {
        $this->assertSame('Chrome on macOS', DeviceInfo::parse(self::MAC_CHROME)['label']);
        $this->assertSame('computer', DeviceInfo::parse(self::MAC_CHROME)['device']);

        $iphone = DeviceInfo::parse(self::IPHONE_SAFARI);
        $this->assertSame('Safari on iOS', $iphone['label']);
        $this->assertSame('phone', $iphone['device']);

        $this->assertSame('Edge on Windows', DeviceInfo::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36 Edg/124.0.0.0')['label']);
        $this->assertSame('Firefox on Linux', DeviceInfo::parse('Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0')['label']);
        $this->assertSame('Chrome on Android', DeviceInfo::parse('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/124.0.0.0 Mobile Safari/537.36')['label']);
        $this->assertSame('tablet', DeviceInfo::parse('Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Version/17.4 Mobile/15E148 Safari/604.1')['device']);
    }

    public function test_an_unreadable_user_agent_still_gives_a_label(): void
    {
        $this->assertSame('Unknown browser on an unknown system', DeviceInfo::parse(null)['label']);
        $this->assertSame('Unknown browser on an unknown system', DeviceInfo::parse('curl')['label']);
    }

    // ------------------------------------------------------------ the list

    public function test_guests_cannot_use_the_device_pages(): void
    {
        $this->put('/account/security/password')->assertRedirect('/');
        $this->delete('/account/security/devices')->assertRedirect('/');
        $this->post('/account/security/devices/sign-out-everywhere')->assertRedirect('/');
        $this->delete('/account/security/devices/'.str_repeat('a', 32))->assertRedirect('/');
    }

    public function test_the_settings_page_lists_the_devices_and_marks_this_one(): void
    {
        $user = User::factory()->create();
        $this->addSession($user, 'other-phone', ['user_agent' => self::IPHONE_SAFARI, 'ip_address' => '198.51.100.9']);

        $this->signedIn($user)->get('/account/security')
            ->assertOk()
            ->assertSee('Devices you are signed in on')
            ->assertSee('Chrome on macOS')
            ->assertSee('Safari on iOS')
            ->assertSee('198.51.100.9')
            ->assertSee('This device')
            ->assertSee('Sign out other devices')
            ->assertSee('Sign out everywhere');
    }

    public function test_the_current_device_comes_first_and_only_the_others_have_a_sign_out_button(): void
    {
        $user = User::factory()->create();
        $this->addSession($user, 'other-phone', ['user_agent' => self::IPHONE_SAFARI, 'last_activity' => now()->addMinute()->timestamp]);
        $this->addSession($user, self::CURRENT, ['last_activity' => now()->subDay()->timestamp]);

        $sessions = app(UserSessions::class)->for($user, self::CURRENT);

        $this->assertTrue($sessions->first()['current'], 'this device is first even though it was active least recently');
        $this->assertFalse($sessions->last()['current']);

        $html = $this->signedIn($user)->get('/account/security')->getContent();
        $this->assertSame(1, preg_match_all('/action="[^"]*account\/security\/devices\/[a-f0-9]{32}"/', $html), 'only the other device has its own sign out button');
    }

    public function test_session_ids_never_reach_the_page_and_other_users_sessions_are_not_listed(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->addSession($user, 'secret-session-id-of-the-user');
        $this->addSession($stranger, 'stranger-session-id', ['ip_address' => '192.0.2.99']);

        $html = $this->signedIn($user)->get('/account/security')->assertOk()->getContent();

        $this->assertStringNotContainsString('secret-session-id-of-the-user', $html);
        $this->assertStringNotContainsString(self::CURRENT, $html);
        $this->assertStringNotContainsString('stranger-session-id', $html);
        $this->assertStringNotContainsString('192.0.2.99', $html);
        $this->assertStringContainsString($this->handle('secret-session-id-of-the-user'), $html);
    }

    // ------------------------------------------------------------ signing out devices

    public function test_one_other_device_can_be_signed_out(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $this->addSession($user, 'other-phone');
        $this->addSession($user, 'other-laptop');

        $this->signedIn($user)->delete('/account/security/devices/'.$this->handle('other-phone'))
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('sessions', ['id' => 'other-phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-laptop']);
        $this->assertDatabaseHas('sessions', ['id' => self::CURRENT]);
        $this->assertNotSame('old-token', $user->fresh()->remember_token, '"stay signed in" cookies stop working');
    }

    public function test_you_cannot_sign_out_the_device_you_are_using_from_the_list(): void
    {
        $user = User::factory()->create();

        $this->signedIn($user)->delete('/account/security/devices/'.$this->handle(self::CURRENT))->assertSessionHasErrors('devices');

        $this->assertDatabaseHas('sessions', ['id' => self::CURRENT]);
    }

    public function test_someone_elses_device_cannot_be_signed_out(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->addSession($stranger, 'stranger-session-id');

        $this->signedIn($user)->delete('/account/security/devices/'.$this->handle('stranger-session-id'))->assertSessionHasErrors('devices');

        $this->assertDatabaseHas('sessions', ['id' => 'stranger-session-id']);
    }

    public function test_an_unknown_or_malformed_handle_does_nothing(): void
    {
        $user = User::factory()->create();

        $this->signedIn($user)->delete('/account/security/devices/'.str_repeat('0', 32))->assertSessionHasErrors('devices');
        $this->signedIn($user)->delete('/settings/devices/not-a-handle')->assertNotFound();
        $this->signedIn($user)->delete('/account/security/devices/'.self::CURRENT)->assertNotFound();
    }

    public function test_all_other_devices_can_be_signed_out_at_once(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->addSession($user, 'other-phone');
        $this->addSession($user, 'other-laptop');
        $this->addSession($stranger, 'stranger-session-id');

        $this->signedIn($user)->delete('/account/security/devices')->assertRedirect(route('account.security'))->assertSessionHas('status');

        $this->assertSame([self::CURRENT], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
        $this->assertDatabaseHas('sessions', ['id' => 'stranger-session-id']);
    }

    public function test_signing_out_everywhere_ends_every_session_including_this_one(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $stranger = User::factory()->create();
        $this->addSession($user, 'other-phone');
        $this->addSession($stranger, 'stranger-session-id');

        $this->signedIn($user)->post('/account/security/devices/sign-out-everywhere')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('sessions', ['id' => 'stranger-session-id']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    // ------------------------------------------------------------ changing the password

    private function change(User $user, array $data)
    {
        return $this->signedIn($user)->put('/account/security/password', $data);
    }

    public function test_changing_the_password_signs_out_the_other_devices_but_not_this_one(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123', 'remember_token' => 'old-token']);
        $this->addSession($user, 'other-phone');

        $this->change($user, ['current_password' => 'old-password-123', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-456', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-phone']);
        $this->assertDatabaseHas('sessions', ['id' => self::CURRENT]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->change($user, ['current_password' => 'wrong-password', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456'])
            ->assertRedirect(route('account.security'))
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_a_weak_mismatched_or_unchanged_password_is_refused(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->change($user, ['current_password' => 'old-password-123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrorsIn('password', 'password');
        $this->change($user, ['current_password' => 'old-password-123', 'password' => 'long-enough-password', 'password_confirmation' => 'other-long-password'])->assertSessionHasErrorsIn('password', 'password');
        $this->change($user, ['current_password' => 'old-password-123', 'password' => 'old-password-123', 'password_confirmation' => 'old-password-123'])->assertSessionHasErrorsIn('password', 'password');

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_the_password_errors_show_on_the_settings_page(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->change($user, ['current_password' => 'nope', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456']);

        $this->signedIn($user, self::CURRENT_TWO)->get('/account/security')->assertOk()->assertSee('Change password');
    }
}
