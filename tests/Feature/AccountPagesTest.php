<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_page_needs_a_login(): void
    {
        foreach (['/account', '/account/profile', '/account/notifications', '/account/work-hours', '/account/security'] as $url) {
            $this->get($url)->assertRedirect('/');
        }
    }

    public function test_the_account_pages_open_and_share_the_same_tabs(): void
    {
        $user = User::factory()->create();

        foreach (['/account/profile', '/account/notifications', '/account/work-hours', '/account/security'] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('aria-label="Account"', false)
                ->assertSee('Profile')
                ->assertSee('Notifications')
                ->assertSee('Work hours')
                ->assertSee('Security');
        }
    }

    public function test_the_account_start_page_leads_to_the_profile(): void
    {
        $this->actingAs(User::factory()->create())->get('/account')->assertRedirect(route('account.profile'));
    }

    public function test_the_old_settings_address_still_works(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings')->assertRedirect('/account');
    }

    public function test_the_old_settings_address_needs_a_login_too(): void
    {
        $this->get('/settings')->assertRedirect('/');
    }

    public function test_each_page_has_only_its_own_topic(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account/work-hours')->assertSee('Working days')->assertDontSee('Current password')->assertDontSee('Devices you are signed in on');
        $this->actingAs($user)->get('/account/security')->assertSee('Current password')->assertSee('Devices you are signed in on')->assertDontSee('Working days');
        $this->actingAs($user)->get('/account/notifications')->assertSee('Turn on notifications')->assertDontSee('Working days')->assertDontSee('Current password');
        $this->actingAs($user)->get('/account/profile')->assertSee('Your details')->assertDontSee('Working days')->assertDontSee('Current password');
    }

    // ------------------------------------------------------------ the profile

    public function test_the_profile_shows_the_details_without_a_form(): void
    {
        $user = User::factory()->create(['name' => 'Anna van Uden', 'email' => 'anna@example.com']);
        $team = Team::factory()->managedBy(User::factory()->create())->create(['name' => 'Service desk']);
        $team->users()->attach($user);

        $html = $this->actingAs($user)->get('/account/profile')->assertOk()
            ->assertSee('Anna van Uden')
            ->assertSee('anna@example.com')
            ->assertSee('Service desk')
            ->assertSee('managed by an admin')
            ->getContent();

        $this->assertStringNotContainsString('name="name"', $html, 'the name cannot be edited here');
        $this->assertStringNotContainsString('type="email"', $html, 'the email cannot be edited here');
        $this->patch('/account/profile', ['name' => 'Hacked'])->assertStatus(405);
    }

    public function test_the_profile_shows_the_role_and_the_team_you_manage(): void
    {
        $manager = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id]);

        $this->actingAs($manager)->get('/account/profile')->assertSee('Team manager')->assertSee('you manage it');
    }

    public function test_someone_without_a_team_is_told_so(): void
    {
        $this->actingAs(User::factory()->create())->get('/account/profile')->assertSee('You are not in a team');
    }

    // ------------------------------------------------------------ the notification hints

    public function test_the_dashboard_has_a_hidden_hint_for_notifications_that_scripts_reveal(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('data-notify-hint hidden', false)
            ->assertSee('data-notify-hint-dismiss', false)
            ->assertSee(route('account.notifications'), false);
    }

    public function test_the_dot_and_the_off_label_are_hidden_until_a_script_shows_them(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data-notify-dot hidden'), 'the hamburger and the profile button each have a dot');
        $this->assertStringContainsString('data-notify-badge hidden', $html);
    }

    public function test_the_hint_is_only_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/reminders')->assertOk()->assertDontSee('data-notify-hint hidden', false);
    }

    public function test_the_admin_panel_has_a_link_back_to_the_account(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('/account', false);
    }
}
