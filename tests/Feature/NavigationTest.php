<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_header_has_a_hamburger_button_that_controls_the_menu(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-menu-button[^>]*aria-expanded="false"[^>]*aria-controls="main-menu"/s', $html);
        $this->assertStringContainsString('id="main-menu"', $html);
        $this->assertStringContainsString('<span class="sr-only">Menu</span>', $html);
    }

    public function test_the_hamburger_menu_has_the_work_pages_and_an_account_section(): void
    {
        $user = User::factory()->create(['name' => 'Marian Manager', 'email' => 'marian@example.com']);
        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        preg_match('/<div id="main-menu".*?<\/header>/s', $html, $menu);

        foreach (['Dashboard', 'Reminders', 'Marian Manager', 'marian@example.com', 'Profile', 'Notifications', 'Work hours', 'Security', 'Sign out'] as $item) {
            $this->assertStringContainsString($item, $menu[0], $item.' should be in the menu');
        }

        $this->assertDoesNotMatchRegularExpression('/<nav[^>]*aria-label="Main".*?Settings.*?<\/nav>/s', $menu[0], 'there is no Settings tab any more');
    }

    public function test_the_work_pages_are_the_main_menu_and_the_account_pages_are_in_the_profile_menu(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();

        preg_match('/<nav[^>]*aria-label="Main".*?<\/nav>/s', $html, $main);
        $this->assertStringNotContainsString('Security', $main[0]);
        $this->assertStringNotContainsString('Work hours', $main[0]);

        $this->assertMatchesRegularExpression('/<button[^>]*data-profile-button[^>]*aria-expanded="false"[^>]*aria-controls="profile-menu"/s', $html);
        $this->assertStringContainsString('id="profile-menu"', $html);
    }

    public function test_the_profile_button_shows_the_initials(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Anna van Uden']))->get('/dashboard')->assertSee('>'."\n".'                            AU', false);
    }

    public function test_initials_use_the_first_and_last_name(): void
    {
        $this->assertSame('AU', User::factory()->make(['name' => 'Anna van Uden'])->initials());
        $this->assertSame('N', User::factory()->make(['name' => 'Nina'])->initials());
        $this->assertSame('ÉB', User::factory()->make(['name' => 'élodie  Bakker '])->initials());
    }

    public function test_the_current_account_page_is_marked_in_the_profile_menu_and_the_tabs(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/account/security')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('account.security'), '/').'"\s+aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a href="'.preg_quote(route('account.profile'), '/').'"\s+aria-current="page"/', $html);
    }

    public function test_the_admin_link_lives_in_the_profile_menu_for_admins_only(): void
    {
        $admin = $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->getContent();
        $normal = $this->actingAs(User::factory()->create())->get('/dashboard')->getContent();

        $this->assertStringContainsString('Admin panel', $admin);
        preg_match('/<nav[^>]*aria-label="Main".*?<\/nav>/s', $admin, $main);
        $this->assertStringNotContainsString('/admin', $main[0], 'the Admin tab moved into the profile menu');
        $this->assertStringNotContainsString('Admin panel', $normal);
        $this->assertStringNotContainsString('href="/admin"', $normal);
    }

    public function test_the_role_is_shown_in_the_profile_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee('Admin');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('User');
    }

    public function test_the_account_links_in_the_hamburger_menu_are_folded_into_one_row(): void
    {
        $html = $this->actingAs(User::factory()->create(['name' => 'Marian Manager', 'email' => 'marian@example.com']))->get('/dashboard')->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-account-toggle[^>]*aria-expanded="false"[^>]*aria-controls="account-section"/s', $html);
        $this->assertMatchesRegularExpression('/<div id="account-section" hidden/', $html, 'the links start folded');

        preg_match('/<div id="account-section".*?<\/header>/s', $html, $section);
        foreach (['Profile', 'Notifications', 'Work hours', 'Security', 'Sign out'] as $item) {
            $this->assertStringContainsString($item, $section[0], $item.' is inside the folded section');
        }

        preg_match('/<button[^>]*data-account-toggle.*?<\/button>/s', $html, $row);
        $this->assertStringContainsString('Marian Manager', $row[0]);
        $this->assertStringContainsString('marian@example.com', $row[0]);
        $this->assertStringContainsString('MM', $row[0]);
    }

    public function test_the_folded_row_is_highlighted_on_an_account_page(): void
    {
        $user = User::factory()->create();

        preg_match('/<button[^>]*data-account-toggle[^>]*class="([^"]*)"/s', $this->actingAs($user)->get('/account/profile')->getContent(), $onAccount);
        preg_match('/<button[^>]*data-account-toggle[^>]*class="([^"]*)"/s', $this->actingAs($user)->get('/dashboard')->getContent(), $elsewhere);

        $this->assertStringContainsString('bg-white/10', $onAccount[1]);
        $this->assertStringNotContainsString('bg-white/10', $elsewhere[1]);
    }
}
