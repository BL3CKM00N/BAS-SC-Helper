<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use App\Services\Reminders\ReminderNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderEngineTest extends TestCase
{
    use RefreshDatabase;

    private ReminderEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ReminderEngine::class);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Europe/Amsterdam')->utc();
    }

    private function recipient(Reminder $reminder, User $user): ReminderRecipient
    {
        return $reminder->recipients()->where('user_id', $user->getKey())->firstOrFail();
    }

    private function team(User ...$users): Team
    {
        $team = Team::factory()->create();

        foreach ($users as $i => $user) {
            $team->users()->attach($user, ['role' => $i === 0 ? Team::MANAGER : Team::MEMBER]);
        }

        return $team;
    }

    // ------------------------------------------------------------ recipients

    public function test_a_personal_reminder_goes_to_its_owner_only(): void
    {
        $owner = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $owner->id]);

        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->assertSame([$owner->id], $reminder->recipients()->pluck('user_id')->all());
    }

    public function test_a_team_reminder_goes_to_everyone_in_the_team(): void
    {
        [$manager, $a, $b] = User::factory()->count(3)->create();
        $team = $this->team($manager, $a, $b);
        $reminder = Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id]);

        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->assertEqualsCanonicalizing([$manager->id, $a->id, $b->id], $reminder->recipients()->pluck('user_id')->all());
    }

    public function test_an_assigned_team_reminder_goes_to_one_person(): void
    {
        [$manager, $a] = User::factory()->count(2)->create();
        $team = $this->team($manager, $a);
        $reminder = Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id, 'assignee_id' => $a->id]);

        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->assertSame([$a->id], $reminder->recipients()->pluck('user_id')->all());
    }

    public function test_new_team_members_get_the_reminder_and_leavers_lose_it(): void
    {
        [$manager, $a, $newcomer] = User::factory()->count(3)->create();
        $team = $this->team($manager, $a);
        $reminder = Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $team->users()->attach($newcomer, ['role' => Team::MEMBER]);
        $team->users()->detach($a);
        $this->engine->syncTeamReminders($this->at('2026-10-26 10:05'));

        $this->assertEqualsCanonicalizing([$manager->id, $newcomer->id], $reminder->recipients()->pluck('user_id')->all());
    }

    // ------------------------------------------------------------ deadlines

    public function test_a_deadline_starts_nagging_at_its_lead_time(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create([
            'created_by' => $user->id,
            'due_at' => $this->at('2026-10-26 16:00'),
            'lead_minutes' => 240,
        ]);

        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));

        $this->assertEquals($this->at('2026-10-26 12:00'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_a_deadline_that_is_close_starts_right_away(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);

        $this->engine->sync($reminder, $this->at('2026-10-26 10:30'));

        $this->assertEquals($this->at('2026-10-26 10:30'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_a_deadline_that_starts_outside_work_hours_waits_for_the_morning(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create([
            'created_by' => $user->id,
            'due_at' => $this->at('2026-10-27 09:00'),
            'lead_minutes' => 240, // would start at 05:00
        ]);

        $this->engine->sync($reminder, $this->at('2026-10-26 20:00'));

        $this->assertEquals($this->at('2026-10-27 08:00'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_the_nag_interval_speeds_up_towards_the_deadline(): void
    {
        $reminder = Reminder::factory()->make(['due_at' => $this->at('2026-10-26 16:00')]);

        $this->assertSame(60, $this->engine->nagInterval($reminder, $this->at('2026-10-26 12:00'))); // 4h left
        $this->assertSame(30, $this->engine->nagInterval($reminder, $this->at('2026-10-26 14:30'))); // 90 min left
        $this->assertSame(10, $this->engine->nagInterval($reminder, $this->at('2026-10-26 15:45'))); // 15 min left
        $this->assertSame(5, $this->engine->nagInterval($reminder, $this->at('2026-10-26 16:01'))); // overdue
    }

    public function test_a_fixed_nag_interval_overrides_the_tiers(): void
    {
        $reminder = Reminder::factory()->make(['due_at' => $this->at('2026-10-26 16:00'), 'nag_minutes' => 20]);

        $this->assertSame(20, $this->engine->nagInterval($reminder, $this->at('2026-10-26 10:00')));
        $this->assertSame(20, $this->engine->nagInterval($reminder, $this->at('2026-10-26 17:00')));
    }

    // ------------------------------------------------------------ sending

    public function test_sending_marks_the_reminder_pending_logs_it_and_schedules_the_next_nag(): void
    {
        $this->mock(ReminderNotifier::class)->shouldReceive('send')->once();
        $engine = app(ReminderEngine::class);

        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 16:00')]);
        $engine->sync($reminder, $this->at('2026-10-26 10:00')); // 6h left, lead 4h -> first at 12:00

        $this->assertSame(0, $engine->sendDue($this->at('2026-10-26 11:59')));
        $this->assertSame(1, $engine->sendDue($this->at('2026-10-26 12:00')));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-26 12:00'), $recipient->pending_since);
        $this->assertEquals($this->at('2026-10-26 13:00'), $recipient->next_notify_at); // 4h left -> 60 min
        $this->assertSame(1, $reminder->events()->where('type', ReminderEvent::NOTIFIED)->count());
    }

    public function test_an_ignored_reminder_keeps_the_original_pending_time(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->engine->sendDue($this->at('2026-10-26 10:00'));
        $this->engine->sendDue($this->at('2026-10-26 10:30'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-26 10:00'), $recipient->pending_since);
        $this->assertSame(2, $reminder->events()->where('type', ReminderEvent::NOTIFIED)->count());
    }

    public function test_nothing_is_sent_outside_work_hours_and_it_waits_for_the_morning(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 23:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 17:00'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 19:00')]);

        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-26 19:00')));

        $this->assertEquals($this->at('2026-10-27 08:00'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame(0, $reminder->events()->count());
    }

    public function test_an_overdue_deadline_can_ignore_work_hours(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create([
            'created_by' => $user->id,
            'due_at' => $this->at('2026-10-26 17:00'),
            'ignore_work_hours_when_overdue' => true,
        ]);
        $this->engine->sync($reminder, $this->at('2026-10-26 16:00'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 19:00')]);

        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-26 19:00')));
        $this->assertEquals($this->at('2026-10-26 19:05'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_completed_and_archived_reminders_are_not_sent(): void
    {
        $user = User::factory()->create();
        $done = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $archived = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $this->engine->sync($done, $this->at('2026-10-26 10:00'));
        $this->engine->sync($archived, $this->at('2026-10-26 10:00'));
        $done->update(['completed_at' => $this->at('2026-10-26 10:10')]);
        $archived->update(['archived_at' => $this->at('2026-10-26 10:10')]);

        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-26 10:30')));
    }

    // ------------------------------------------------------------ done

    public function test_marking_a_personal_deadline_done_completes_it(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));
        $this->engine->sendDue($this->at('2026-10-26 10:00'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 10:20'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertNotNull($recipient->completed_at);
        $this->assertNull($recipient->next_notify_at);
        $this->assertNull($recipient->pending_since);
        $this->assertNotNull($reminder->fresh()->completed_at);
        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-26 11:30')));
    }

    public function test_when_anyone_finishes_a_team_deadline_it_is_done_for_everyone(): void
    {
        [$manager, $a, $b] = User::factory()->count(3)->create();
        $team = $this->team($manager, $a, $b);
        $reminder = Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id, 'completion_mode' => Reminder::ANYONE]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->engine->done($this->recipient($reminder, $a), $this->at('2026-10-26 10:20'));

        $this->assertSame(3, $reminder->recipients()->whereNotNull('completed_at')->count());
        $this->assertNotNull($reminder->fresh()->completed_at);
        $this->assertSame([$a->id], $reminder->events()->where('type', ReminderEvent::DONE)->pluck('user_id')->all());
    }

    public function test_when_each_has_to_finish_a_team_deadline_it_completes_after_the_last_one(): void
    {
        [$manager, $a] = User::factory()->count(2)->create();
        $team = $this->team($manager, $a);
        $reminder = Reminder::factory()->create(['created_by' => $manager->id, 'team_id' => $team->id, 'completion_mode' => Reminder::EACH]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));

        $this->engine->done($this->recipient($reminder, $a), $this->at('2026-10-26 10:20'));

        $this->assertNull($reminder->fresh()->completed_at);
        $this->assertNull($this->recipient($reminder, $manager)->completed_at);

        $this->engine->done($this->recipient($reminder, $manager), $this->at('2026-10-26 10:30'));

        $this->assertNotNull($reminder->fresh()->completed_at);
    }

    // ------------------------------------------------------------ repeating

    public function test_a_repeating_reminder_comes_back_an_interval_after_it_was_done(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(90)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));
        $this->engine->sendDue($this->at('2026-10-26 09:00'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 09:40'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-26 11:10'), $recipient->next_notify_at); // rolling: 09:40 + 90 min
        $this->assertNull($recipient->pending_since);
        $this->assertNull($reminder->fresh()->completed_at);
    }

    public function test_a_repeating_reminder_that_comes_due_after_work_waits_for_the_morning(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(120)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 16:30'));

        $this->assertEquals($this->at('2026-10-27 08:00'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_reaching_the_daily_target_pauses_until_the_next_workday(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(60, 2)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 09:05'));
        $this->assertEquals($this->at('2026-10-26 10:05'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame(1, $this->engine->doneToday($reminder, $user, $this->at('2026-10-26 09:10')));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 10:10'));
        $this->assertEquals($this->at('2026-10-27 08:00'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame(2, $this->engine->doneToday($reminder, $user, $this->at('2026-10-26 10:15')));
    }

    public function test_the_daily_count_starts_over_each_day(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(60, 2)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));
        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 09:05'));

        $this->assertSame(0, $this->engine->doneToday($reminder, $user, $this->at('2026-10-27 09:00')));
    }

    public function test_a_shared_repeating_timer_resets_for_everyone(): void
    {
        [$manager, $a] = User::factory()->count(2)->create();
        $team = $this->team($manager, $a);
        $reminder = Reminder::factory()->repeating(60)->create(['created_by' => $manager->id, 'team_id' => $team->id, 'completion_mode' => Reminder::ANYONE]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));

        $this->engine->done($this->recipient($reminder, $a), $this->at('2026-10-26 09:10'));

        $this->assertEquals($this->at('2026-10-26 10:10'), $this->recipient($reminder, $manager)->next_notify_at);
        $this->assertEquals($this->at('2026-10-26 10:10'), $this->recipient($reminder, $a)->next_notify_at);
        $this->assertSame(1, $this->engine->doneToday($reminder, $manager, $this->at('2026-10-26 09:15')));
    }

    public function test_separate_repeating_timers_only_reset_for_the_one_who_acted(): void
    {
        [$manager, $a] = User::factory()->count(2)->create();
        $team = $this->team($manager, $a);
        $reminder = Reminder::factory()->repeating(60)->create(['created_by' => $manager->id, 'team_id' => $team->id, 'completion_mode' => Reminder::EACH]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));

        $this->engine->done($this->recipient($reminder, $a), $this->at('2026-10-26 09:10'));

        $this->assertEquals($this->at('2026-10-26 10:10'), $this->recipient($reminder, $a)->next_notify_at);
        $this->assertEquals($this->at('2026-10-26 09:00'), $this->recipient($reminder, $manager)->next_notify_at);
        $this->assertSame(1, $this->engine->doneToday($reminder, $a, $this->at('2026-10-26 09:15')));
        $this->assertSame(0, $this->engine->doneToday($reminder, $manager, $this->at('2026-10-26 09:15')));
    }

    // ------------------------------------------------------------ snooze and skip

    public function test_snoozing_pushes_the_reminder_back_and_clears_pending(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));
        $this->engine->sendDue($this->at('2026-10-26 10:00'));

        $this->engine->snooze($this->recipient($reminder, $user), 30, $this->at('2026-10-26 10:05'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-26 10:35'), $recipient->next_notify_at);
        $this->assertEquals($this->at('2026-10-26 10:35'), $recipient->snoozed_until);
        $this->assertNull($recipient->pending_since);
        $this->assertSame(0, $this->engine->dueNow($user, $this->at('2026-10-26 10:20'))->count());
        $this->assertSame(1, $this->engine->dueNow($user, $this->at('2026-10-26 10:36'))->count());
    }

    public function test_snoozing_late_in_the_day_waits_for_the_morning(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(60)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 17:00'));

        $this->engine->snooze($this->recipient($reminder, $user), 60, $this->at('2026-10-26 17:20'));

        $this->assertEquals($this->at('2026-10-27 08:00'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_skipping_today_hides_it_until_the_next_workday(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(60)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 09:00'));
        $this->engine->sendDue($this->at('2026-10-26 09:00'));

        $this->engine->skipToday($this->recipient($reminder, $user), $this->at('2026-10-26 09:10'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-27 08:00'), $recipient->next_notify_at);
        $this->assertNull($recipient->pending_since);
        $this->assertSame(0, $this->engine->dueNow($user, $this->at('2026-10-26 16:00'))->count());
        $this->assertSame(1, $this->engine->dueNow($user, $this->at('2026-10-27 08:01'))->count());
    }

    // ------------------------------------------------------------ the due list

    public function test_the_due_list_has_pending_and_ready_reminders_only_for_the_user(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $mine = Reminder::factory()->create(['created_by' => $user->id, 'title' => 'Mine', 'due_at' => $this->at('2026-10-26 11:00')]);
        $later = Reminder::factory()->create(['created_by' => $user->id, 'title' => 'Later', 'due_at' => $this->at('2026-10-28 11:00')]);
        $theirs = Reminder::factory()->create(['created_by' => $other->id, 'title' => 'Theirs', 'due_at' => $this->at('2026-10-26 11:00')]);

        foreach ([$mine, $later, $theirs] as $reminder) {
            $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));
        }

        $titles = $this->engine->dueNow($user, $this->at('2026-10-26 10:00'))->map(fn ($r) => $r->reminder->title)->all();

        $this->assertSame(['Mine'], $titles);
    }

    public function test_the_due_list_is_empty_outside_work_hours_unless_something_is_pending(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 17:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 16:00'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 16:00')]);

        $this->assertSame(0, $this->engine->dueNow($user, $this->at('2026-10-26 19:00'))->count());

        $this->recipient($reminder, $user)->update(['pending_since' => $this->at('2026-10-26 17:00')]);

        $this->assertSame(1, $this->engine->dueNow($user, $this->at('2026-10-26 19:00'))->count());
    }

    public function test_rescheduling_starts_a_reminder_over(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => $this->at('2026-10-26 11:00')]);
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));
        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 10:05'));

        $reminder->update(['due_at' => $this->at('2026-10-26 15:00')]);
        $this->engine->reschedule($reminder, $this->at('2026-10-26 10:10'));

        $this->assertNull($reminder->fresh()->completed_at);
        $this->assertNull($this->recipient($reminder, $user)->completed_at);
        $this->assertEquals($this->at('2026-10-26 11:00'), $this->recipient($reminder, $user)->next_notify_at); // 15:00 - 4h
    }

    public function test_the_send_command_runs_and_reports(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['created_by' => $user->id, 'due_at' => now()->addMinutes(20)]);
        $this->engine->sync($reminder, now());
        $this->recipient($reminder, $user)->update(['next_notify_at' => now()->subMinute()]);

        $this->travelTo($this->at('2026-10-26 10:00'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 09:59')]);
        $reminder->update(['due_at' => $this->at('2026-10-26 10:20')]);

        $this->artisan('reminders:send')->expectsOutput('Sent 1 reminder(s).')->assertSuccessful();
    }
}
