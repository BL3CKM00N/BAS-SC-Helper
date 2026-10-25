<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemInfo;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\SystemHealthOverview;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\SystemHealth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id]);
    }

    public function test_scheduler_is_stale_without_a_beat_and_alive_after_one(): void
    {
        $health = app(SystemHealth::class);
        $this->assertFalse($health->schedulerIsAlive());

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertTrue($health->schedulerIsAlive());

        $this->travel(4)->minutes();
        $this->assertFalse($health->schedulerIsAlive());
    }

    public function test_dashboard_widgets_render_for_an_admin(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(NeedsAttention::class)->assertSee('Pending requests')->assertSee('Temporary passwords not changed');
        Livewire::actingAs($admin)->test(SystemHealthOverview::class)->assertSee('Reminder scheduler')->assertSee('Stopped');
    }

    public function test_system_info_page_renders(): void
    {
        Livewire::actingAs($this->admin())->test(SystemInfo::class)->assertSee('Laravel')->assertSee('PHP');
    }

    public function test_push_failures_are_counted(): void
    {
        $health = app(SystemHealth::class);
        $health->pushFailed('boom');
        $health->pushFailed('again');

        $this->assertSame(2, $health->lastPushFailure()['count']);
    }
}
