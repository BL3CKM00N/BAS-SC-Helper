<?php

namespace Tests\Feature;

use App\Filament\Pages\NotificationReach;
use App\Filament\Pages\TeamActivity;
use App\Models\Reminder;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TestPush;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id]);
    }

    public function test_reach_lists_people_without_notifications_by_default(): void
    {
        $admin = $this->admin();
        $with = User::factory()->create(['name' => 'Has Device']);
        $with->updatePushSubscription('https://push.example.com/send/1', 'k', 't');
        User::factory()->create(['name' => 'No Device']);

        Livewire::actingAs($admin)->test(NotificationReach::class)
            ->assertSee('No Device')
            ->assertDontSee('Has Device')
            ->assertSee('1 of 3');
    }

    public function test_an_admin_can_send_a_test_push_to_someone_with_a_device(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example.com/send/1', 'k', 't');

        Livewire::actingAs($admin)->test(NotificationReach::class)
            ->removeTableFilter('none')
            ->callTableAction('test', $user);

        Notification::assertSentTo($user, TestPush::class);
    }

    public function test_the_account_test_button_still_works(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example.com/send/1', 'k', 't');

        $this->actingAs($user)->post(route('push.test'))->assertOk();

        Notification::assertSentTo($user, TestPush::class);
    }

    public function test_team_activity_counts_team_reminders_only(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->create();
        $team = Team::factory()->managedBy($manager)->create(['name' => 'Alpha team']);
        Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id, 'due_at' => now()->subDay()]);
        Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => null, 'title' => 'Secret personal thing']);

        Livewire::actingAs($admin)->test(TeamActivity::class)
            ->assertSee('Alpha team')
            ->assertDontSee('Secret personal thing')
            ->assertSeeInOrder(['Alpha team', '1', '1', '1']);
    }
}
