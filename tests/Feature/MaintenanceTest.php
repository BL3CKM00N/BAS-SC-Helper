<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenancePage;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Maintenance;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id, 'password' => 'secret-pass-1']);
    }

    private function on(): void
    {
        app(Maintenance::class)->enable('Updating the database.', now()->addHour());
    }

    public function test_everything_works_normally_when_it_is_off(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    }

    public function test_signed_in_people_get_the_maintenance_page(): void
    {
        $this->on();

        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertSee('Under maintenance')
            ->assertSee('Updating the database.');
    }

    public function test_admins_are_not_locked_out(): void
    {
        $this->on();

        $this->actingAs($this->admin())->get('/dashboard')->assertOk()->assertSee('Maintenance mode is on');
    }

    public function test_the_login_page_shows_a_notice_and_only_admins_can_sign_in(): void
    {
        $this->on();
        $user = User::factory()->create(['password' => 'secret-pass-1']);
        $admin = $this->admin();

        $this->get('/')->assertOk()->assertSee('Updating the database.');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $admin->email, 'password' => 'secret-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_health_check_tells_the_browser_about_maintenance(): void
    {
        $this->get('/up')->assertOk()->assertHeaderMissing('X-Maintenance');

        $this->on();

        $this->get('/up')->assertOk()->assertHeader('X-Maintenance');
    }

    public function test_pages_mark_who_is_locked_out(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('data-audience="member"', false);
        $this->actingAs($this->admin())->get('/dashboard')->assertDontSee('data-audience', false);
    }

    public function test_signing_out_still_works(): void
    {
        $this->on();

        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('login'));
    }

    public function test_reminders_keep_being_sent_during_maintenance(): void
    {
        $this->on();

        $this->artisan('reminders:send')->assertSuccessful();
    }

    public function test_commands_and_the_page_switch_it_on_and_off(): void
    {
        $this->artisan('maintenance:on', ['--message' => 'Back soon', '--minutes' => 30])->assertSuccessful();
        $this->assertTrue(app(Maintenance::class)->isOn());

        $this->artisan('maintenance:off')->assertSuccessful();
        $this->assertFalse(app(Maintenance::class)->isOn());

        Livewire::actingAs($this->admin())->test(MaintenancePage::class)
            ->fillForm(['on' => true, 'message' => 'From the panel'])
            ->call('save');

        $this->assertTrue(app(Maintenance::class)->isOn());
        $this->assertSame('From the panel', app(Maintenance::class)->message());
        $this->assertTrue(AuditLog::query()->where('action', 'maintenance.enabled')->exists());
    }
}
