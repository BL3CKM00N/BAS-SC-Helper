<?php

namespace Tests\Feature;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attributes = []): Announcement
    {
        return Announcement::query()->create($attributes + ['title' => 'Heads up', 'body' => 'Details here', 'severity' => 'info', 'audience' => 'everyone']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::TEAM_MANAGER])->id]);
    }

    public function test_everyone_sees_an_active_announcement_on_their_dashboard(): void
    {
        $this->make();

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('Heads up')->assertSee('Details here');
    }

    public function test_timing_decides_when_it_shows(): void
    {
        $this->make(['title' => 'Later', 'starts_at' => now()->addHour()]);
        $this->make(['title' => 'Over', 'ends_at' => now()->subMinute()]);
        $this->make(['title' => 'Now', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

        $names = Announcement::visibleFor(User::factory()->create())->pluck('title')->all();

        $this->assertSame(['Now'], $names);
    }

    public function test_audience_limits_who_sees_it(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->users()->attach($member);
        $outsider = User::factory()->create();
        $manager = $this->manager();

        $this->make(['title' => 'For the team', 'audience' => 'team', 'team_id' => $team->id]);
        $this->make(['title' => 'For managers', 'audience' => 'managers']);

        $this->assertSame(['For the team'], Announcement::visibleFor($member)->pluck('title')->all());
        $this->assertSame([], Announcement::visibleFor($outsider)->pluck('title')->all());
        $this->assertSame(['For managers'], Announcement::visibleFor($manager)->pluck('title')->all());
    }

    public function test_a_person_can_dismiss_but_critical_ones_stay(): void
    {
        $user = User::factory()->create();
        $info = $this->make(['title' => 'Info']);
        $critical = $this->make(['title' => 'Critical', 'severity' => 'critical']);

        $this->actingAs($user)->post(route('announcements.dismiss', $info))->assertRedirect();
        $this->actingAs($user)->post(route('announcements.dismiss', $critical))->assertForbidden();

        $this->assertSame(['Critical'], Announcement::visibleFor($user)->pluck('title')->all());
        $this->assertSame(['Info'], Announcement::visibleFor(User::factory()->create())->pluck('title')->sort()->values()->diff(['Critical'])->values()->all());
    }

    public function test_the_banner_fragment_is_served_for_polling(): void
    {
        $this->make();

        $this->actingAs(User::factory()->create())->get(route('announcements.index'))->assertOk()->assertSee('Heads up')->assertDontSee('<html', false);
    }

    public function test_critical_comes_first(): void
    {
        $this->make(['title' => 'Info']);
        $this->make(['title' => 'Critical', 'severity' => 'critical']);

        $this->assertSame(['Critical', 'Info'], Announcement::visibleFor(User::factory()->create())->pluck('title')->all());
    }

    public function test_push_is_sent_once_to_the_audience_only(): void
    {
        Notification::fake();
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->users()->attach($member);
        $outsider = User::factory()->create();
        $disabled = User::factory()->create(['disabled_at' => now()]);
        $team->users()->attach($disabled);
        foreach ([$member, $outsider, $disabled] as $i => $person) {
            $person->updatePushSubscription('https://push.example.com/send/'.$i, 'p256dh-key', 'auth-token');
        }
        $this->make(['audience' => 'team', 'team_id' => $team->id, 'push' => true]);

        $this->artisan('announcements:publish')->assertSuccessful();
        $this->artisan('announcements:publish')->assertSuccessful();

        Notification::assertSentToTimes($member, AnnouncementPublished::class, 1);
        Notification::assertNotSentTo($outsider, AnnouncementPublished::class);
        Notification::assertNotSentTo($disabled, AnnouncementPublished::class);
    }

    public function test_a_scheduled_push_waits_for_the_start(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example.com/send/x', 'p256dh-key', 'auth-token');
        $this->make(['push' => true, 'starts_at' => now()->addHour()]);

        $this->artisan('announcements:publish');

        Notification::assertNothingSent();

        $this->travel(2)->hours();
        $this->artisan('announcements:publish');

        Notification::assertSentTo($user, AnnouncementPublished::class);
    }

    public function test_admins_create_announcements_in_the_panel_and_it_is_audited(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => Role::ADMIN])->id]);

        Livewire::actingAs($admin)->test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Planned downtime', 'severity' => 'warning', 'audience' => 'everyone', 'push' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($admin->id, Announcement::query()->firstOrFail()->created_by);
        $this->assertTrue(AuditLog::query()->where('action', 'announcement.created')->exists());
    }
}
