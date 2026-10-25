<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderAccessTest extends TestCase
{
    use RefreshDatabase;

    private function teamWith(User $manager, User ...$members): Team
    {
        $team = Team::factory()->create();
        $team->users()->attach($manager, ['role' => Team::MANAGER]);

        foreach ($members as $member) {
            $team->users()->attach($member, ['role' => Team::MEMBER]);
        }

        return $team;
    }

    private function make(array $attributes = []): Reminder
    {
        $reminder = Reminder::factory()->create($attributes);
        app(ReminderEngine::class)->sync($reminder);

        return $reminder;
    }

    // ------------------------------------------------------------ who sees what

    public function test_guests_are_sent_to_the_login_card(): void
    {
        $this->get('/reminders')->assertRedirect('/');
        $this->get('/reminders/due')->assertRedirect('/');
        $this->get('/settings')->assertRedirect('/');
    }

    public function test_a_personal_reminder_is_visible_to_its_owner_only(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $reminder = $this->make(['created_by' => $owner->id, 'title' => 'Secret chore']);

        $this->actingAs($owner)->get('/reminders')->assertOk()->assertSee('Secret chore');
        $this->actingAs($other)->get('/reminders')->assertOk()->assertDontSee('Secret chore');
        $this->actingAs(User::factory()->admin()->create())->get('/reminders')->assertDontSee('Secret chore');

        $this->actingAs($other)->get(route('reminders.edit', $reminder))->assertForbidden();
        $this->actingAs($other)->post(route('reminders.done', $reminder))->assertForbidden();
    }

    public function test_a_team_reminder_is_visible_to_team_members_only(): void
    {
        [$manager, $member, $outsider] = User::factory()->count(3)->create();
        $team = $this->teamWith($manager, $member);
        $this->make(['created_by' => $manager->id, 'team_id' => $team->id, 'title' => 'Team chore']);

        $this->actingAs($member)->get('/reminders')->assertSee('Team chore');
        $this->actingAs($outsider)->get('/reminders')->assertDontSee('Team chore');
    }

    public function test_the_filter_limits_the_list(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWith($user);
        $this->make(['created_by' => $user->id, 'title' => 'Personal one']);
        $this->make(['created_by' => $user->id, 'team_id' => $team->id, 'title' => 'Team one']);

        $this->actingAs($user)->get('/reminders?scope=mine')->assertSee('Personal one')->assertDontSee('Team one');
        $this->actingAs($user)->get('/reminders?scope=team:'.$team->id)->assertSee('Team one')->assertDontSee('Personal one');
        $this->actingAs($user)->get('/reminders?scope=team:9999')->assertSee('Personal one')->assertSee('Team one');
    }

    public function test_due_reminders_show_on_the_dashboard_and_in_the_live_endpoint(): void
    {
        $user = User::factory()->create();
        $reminder = $this->make(['created_by' => $user->id, 'title' => 'Call the garage', 'due_at' => now()->addMinutes(20)]);
        $reminder->recipients()->update(['next_notify_at' => now()->subMinute()]);
        $this->travelTo(now()->setTime(11, 0)->next('Tuesday')); // inside work hours
        $reminder->recipients()->update(['next_notify_at' => now()->subMinute(), 'pending_since' => now()->subMinute()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Due now')->assertSee('Call the garage');

        $this->actingAs($user)->getJson('/reminders/due')
            ->assertOk()
            ->assertJsonPath('ids.0', $reminder->id)
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Call the garage'));
    }

    public function test_skipping_asks_for_confirmation_but_done_and_snooze_do_not(): void
    {
        $user = User::factory()->create();
        $reminder = $this->make(['created_by' => $user->id, 'title' => 'Call the "garage"', 'due_at' => now()->addMinutes(10)]);
        $reminder->recipients()->update(['pending_since' => now()]);

        $html = $this->actingAs($user)->getJson('/reminders/due')->json('html');

        preg_match('/<form[^>]*reminders\/'.$reminder->id.'\/skip[^>]*>/s', $html, $skip);
        preg_match('/<form[^>]*reminders\/'.$reminder->id.'\/done[^>]*>/s', $html, $done);
        preg_match('/<form[^>]*reminders\/'.$reminder->id.'\/snooze[^>]*>/s', $html, $snooze);

        $this->assertStringContainsString('confirm(', $skip[0]);
        $this->assertStringContainsString('until the next working day', $skip[0]);
        $this->assertStringNotContainsString('confirm(', $done[0]);
        $this->assertStringNotContainsString('confirm(', $snooze[0]);
    }

    // ------------------------------------------------------------ creating

    private function deadline(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Send the report',
            'type' => 'deadline',
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'lead_minutes' => 240,
        ];
    }

    public function test_anyone_can_create_a_personal_reminder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reminders', $this->deadline())->assertRedirect(route('reminders.index'));

        $reminder = Reminder::query()->firstOrFail();
        $this->assertSame($user->id, $reminder->created_by);
        $this->assertNull($reminder->team_id);
        $this->assertSame([$user->id], $reminder->recipients()->pluck('user_id')->all());
    }

    public function test_a_deadline_in_the_past_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/reminders', $this->deadline(['due_at' => now()->subHour()->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('due_at');

        $this->assertSame(0, Reminder::query()->count());
    }

    public function test_a_deadline_needs_a_date_and_a_repeating_reminder_needs_an_interval(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reminders', ['title' => 'x', 'type' => 'deadline'])->assertSessionHasErrors('due_at');
        $this->actingAs($user)->post('/reminders', ['title' => 'x', 'type' => 'repeating'])->assertSessionHasErrors('interval_minutes');
    }

    public function test_it_creates_a_repeating_reminder_with_a_daily_target(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reminders', ['title' => 'Check inbox', 'type' => 'repeating', 'interval_minutes' => 90, 'daily_target' => 4])
            ->assertSessionHasNoErrors();

        $reminder = Reminder::query()->firstOrFail();
        $this->assertTrue($reminder->isRepeating());
        $this->assertSame(90, $reminder->interval_minutes);
        $this->assertSame(4, $reminder->daily_target);
        $this->assertNull($reminder->due_at);
    }

    public function test_managers_can_create_team_reminders_and_members_cannot_by_default(): void
    {
        [$manager, $member] = User::factory()->count(2)->create();
        $team = $this->teamWith($manager, $member);

        $this->actingAs($member)->post('/reminders', $this->deadline(['team_id' => $team->id]))->assertForbidden();
        $this->actingAs($manager)->post('/reminders', $this->deadline(['team_id' => $team->id]))->assertRedirect();

        $this->assertSame(2, Reminder::query()->firstOrFail()->recipients()->count());
    }

    public function test_members_can_create_team_reminders_when_the_team_allows_it(): void
    {
        [$manager, $member] = User::factory()->count(2)->create();
        $team = $this->teamWith($manager, $member);
        $team->update(['members_can_create' => true]);

        $this->actingAs($member)->post('/reminders', $this->deadline(['team_id' => $team->id]))->assertRedirect();

        $this->assertSame($member->id, Reminder::query()->firstOrFail()->created_by);
    }

    public function test_outsiders_cannot_create_reminders_for_a_team(): void
    {
        $team = $this->teamWith(User::factory()->create());

        $this->actingAs(User::factory()->create())->post('/reminders', $this->deadline(['team_id' => $team->id]))->assertForbidden();
    }

    public function test_the_assignee_must_be_in_the_team(): void
    {
        [$manager, $member, $outsider] = User::factory()->count(3)->create();
        $team = $this->teamWith($manager, $member);

        $this->actingAs($manager)->post('/reminders', $this->deadline(['team_id' => $team->id, 'assignee_id' => $outsider->id]))->assertSessionHasErrors('assignee_id');
        $this->actingAs($manager)->post('/reminders', $this->deadline(['team_id' => $team->id, 'assignee_id' => $member->id]))->assertSessionHasNoErrors();

        $this->assertSame([$member->id], Reminder::query()->firstOrFail()->recipients()->pluck('user_id')->all());
    }

    public function test_the_forms_render(): void
    {
        $user = User::factory()->create();
        $this->teamWith($user);

        $this->actingAs($user)->get('/reminders/create')->assertOk()->assertSee('Deadline')->assertSee('Repeats during the day');
        $this->actingAs($user)->get(route('reminders.edit', $this->make(['created_by' => $user->id])))->assertOk();
    }

    // ------------------------------------------------------------ editing and deleting

    public function test_the_owner_can_edit_and_delete_a_personal_reminder_but_others_cannot(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $reminder = $this->make(['created_by' => $owner->id, 'title' => 'Old title']);
        $payload = $this->deadline(['title' => 'New title']);

        $this->actingAs($other)->patch(route('reminders.update', $reminder), $payload)->assertForbidden();
        $this->actingAs($other)->delete(route('reminders.destroy', $reminder))->assertForbidden();

        $this->actingAs($owner)->patch(route('reminders.update', $reminder), $payload)->assertSessionHasNoErrors();
        $this->assertSame('New title', $reminder->fresh()->title);

        $this->actingAs($owner)->delete(route('reminders.destroy', $reminder))->assertRedirect();
        $this->assertModelMissing($reminder);
    }

    public function test_team_reminders_can_be_changed_by_a_manager_or_the_creator_only(): void
    {
        [$manager, $creator, $member] = User::factory()->count(3)->create();
        $team = $this->teamWith($manager, $creator, $member);
        $reminder = $this->make(['created_by' => $creator->id, 'team_id' => $team->id]);
        $payload = $this->deadline(['title' => 'Edited']);

        $this->actingAs($member)->patch(route('reminders.update', $reminder), $payload)->assertForbidden();
        $this->actingAs($member)->delete(route('reminders.destroy', $reminder))->assertForbidden();

        $this->actingAs($creator)->patch(route('reminders.update', $reminder), $payload)->assertSessionHasNoErrors();
        $this->actingAs($manager)->patch(route('reminders.update', $reminder), $this->deadline(['title' => 'By manager']))->assertSessionHasNoErrors();
        $this->assertSame('By manager', $reminder->fresh()->title);

        $this->actingAs($manager)->delete(route('reminders.destroy', $reminder))->assertRedirect();
        $this->assertModelMissing($reminder);
    }

    public function test_changing_the_deadline_reschedules_but_changing_the_notes_does_not(): void
    {
        $user = User::factory()->create();
        $reminder = $this->make(['created_by' => $user->id, 'due_at' => now()->addDays(3)->startOfMinute(), 'lead_minutes' => 240, 'notes' => null]);
        $recipient = $reminder->recipients()->firstOrFail();
        $recipient->update(['completed_at' => now()]);

        $this->actingAs($user)->patch(route('reminders.update', $reminder), $this->deadline([
            'due_at' => $reminder->due_at->setTimezone($user->timezone)->format('Y-m-d\TH:i'),
            'title' => $reminder->title,
            'notes' => 'Just a note',
        ]));
        $this->assertNotNull($recipient->fresh()->completed_at, 'a note change must keep the progress');

        $this->actingAs($user)->patch(route('reminders.update', $reminder), $this->deadline(['title' => $reminder->title]));
        $this->assertNull($reminder->recipients()->firstOrFail()->completed_at, 'a new deadline starts over');
    }

    // ------------------------------------------------------------ acting on a reminder

    public function test_done_snooze_and_skip_work_for_the_recipient(): void
    {
        $user = User::factory()->create();
        $reminder = $this->make(['created_by' => $user->id, 'due_at' => now()->addMinutes(15)]);

        $this->actingAs($user)->postJson(route('reminders.snooze', $reminder), ['minutes' => 30])->assertOk();
        $this->assertNotNull($reminder->recipients()->firstOrFail()->snoozed_until);

        $this->actingAs($user)->postJson(route('reminders.skip', $reminder))->assertOk();
        $this->assertNotNull($reminder->recipients()->firstOrFail()->skipped_until);

        $this->actingAs($user)->post(route('reminders.done', $reminder))->assertRedirect();
        $this->assertNotNull($reminder->fresh()->completed_at);
    }

    public function test_snooze_only_accepts_the_offered_durations(): void
    {
        $user = User::factory()->create();
        $reminder = $this->make(['created_by' => $user->id]);

        $this->actingAs($user)->postJson(route('reminders.snooze', $reminder), ['minutes' => 7])->assertUnprocessable();
        $this->actingAs($user)->postJson(route('reminders.snooze', $reminder))->assertUnprocessable();
    }

    public function test_someone_who_is_not_a_recipient_cannot_act_on_a_team_reminder(): void
    {
        [$manager, $assignee, $other] = User::factory()->count(3)->create();
        $team = $this->teamWith($manager, $assignee, $other);
        $reminder = $this->make(['created_by' => $manager->id, 'team_id' => $team->id, 'assignee_id' => $assignee->id]);

        $this->actingAs($other)->post(route('reminders.done', $reminder))->assertNotFound();
        $this->assertNull($reminder->fresh()->completed_at);

        $this->actingAs($assignee)->post(route('reminders.done', $reminder))->assertRedirect();
        $this->assertNotNull($reminder->fresh()->completed_at);
    }

    // ------------------------------------------------------------ settings

    public function test_users_can_change_their_work_hours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Work hours');

        $this->actingAs($user)->patch('/settings', [
            'timezone' => 'Europe/London',
            'work_days' => [1, 2, 3, 4],
            'work_start' => '07:30',
            'work_end' => '16:00',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Europe/London', $user->timezone);
        $this->assertSame('1,2,3,4', $user->work_days);
        $this->assertSame('07:30', $user->work_start);
        $this->assertSame('16:00', $user->work_end);
    }

    public function test_invalid_work_hours_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings', ['timezone' => 'Nowhere/Land', 'work_days' => [], 'work_start' => '18:00', 'work_end' => '09:00'])
            ->assertSessionHasErrors(['timezone', 'work_days', 'work_end']);

        $this->assertSame('08:00', $user->fresh()->work_start);
    }
}
