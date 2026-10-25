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

    public function test_the_links_the_user_name_and_sign_out_live_inside_the_menu(): void
    {
        $user = User::factory()->create(['name' => 'Marian Manager']);
        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        preg_match('/<div id="main-menu".*?<\/header>/s', $html, $menu);

        foreach (['Dashboard', 'Reminders', 'Settings', 'Marian Manager', 'Sign out'] as $item) {
            $this->assertStringContainsString($item, $menu[0], $item.' should be in the menu');
        }
    }

    public function test_the_current_page_is_marked_in_the_menu(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/settings')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('settings.edit'), '/').'"\s+aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a href="'.preg_quote(route('dashboard'), '/').'"\s+aria-current="page"/', $html);
    }

    public function test_admins_get_the_admin_link_in_the_menu_and_others_do_not(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee('href="/admin"', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('href="/admin"', false);
    }
}
