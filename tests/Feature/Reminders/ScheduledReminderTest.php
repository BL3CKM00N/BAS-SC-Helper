<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\User;
use App\Services\Reminders\ReminderEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reminders "on chosen days". The default user works Monday to Friday, 08:00 to 17:30 (Europe/Amsterdam),
 * so the first notification of a chosen day is at 08:15. 26 October 2026 is a Monday.
 */
class ScheduledReminderTest extends TestCase
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

    /**
     * @param  list<int>  $weekdays
     */
    private function weekly(User $user, array $weekdays, string $createdAt): Reminder
    {
        $reminder = Reminder::factory()->onWeekdays($weekdays)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at($createdAt));

        return $reminder;
    }

    public function test_the_first_notification_is_fifteen_minutes_after_the_work_day_starts(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 3], '2026-10-26 07:00');

        $this->assertEquals($this->at('2026-10-26 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_created_later_on_a_chosen_day_it_starts_right_away(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1], '2026-10-26 10:00');

        $this->assertEquals($this->at('2026-10-26 10:00'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_created_after_the_work_day_it_waits_for_the_next_chosen_day(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 3], '2026-10-26 18:00');

        $this->assertEquals($this->at('2026-10-28 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_a_chosen_day_that_is_not_a_work_day_still_fires(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [6], '2026-10-26 09:00'); // Saturday

        $this->assertEquals($this->at('2026-10-31 08:15'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-31 08:14')));
        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-31 08:15')));
    }

    public function test_nothing_is_sent_on_days_that_were_not_chosen(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [3], '2026-10-26 09:00');
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-27 09:00')]); // a Tuesday

        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-27 09:00')));
        $this->assertEquals($this->at('2026-10-28 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_it_nags_during_the_day_and_hands_over_to_the_next_chosen_day(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 3], '2026-10-26 07:00');

        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-26 08:15')));
        $this->assertEquals($this->at('2026-10-26 08:25'), $this->recipient($reminder, $user)->next_notify_at);

        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 17:25')]);
        $this->engine->sendDue($this->at('2026-10-26 17:25'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertEquals($this->at('2026-10-28 08:15'), $recipient->next_notify_at, 'past 17:30 the next nag is the next chosen day');
        $this->assertNotNull($recipient->pending_since, 'it stays on the due list until it is done');
    }

    public function test_done_brings_it_back_on_the_next_chosen_day(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 3], '2026-10-26 07:00');
        $this->engine->sendDue($this->at('2026-10-26 08:15'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 09:00'));

        $recipient = $this->recipient($reminder, $user);
        $this->assertNull($recipient->pending_since);
        $this->assertEquals($this->at('2026-10-28 08:15'), $recipient->next_notify_at);
        $this->assertNull($reminder->fresh()->completed_at, 'a scheduled reminder never completes');
    }

    public function test_skip_today_jumps_to_the_next_chosen_day(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 2], '2026-10-26 07:00');
        $this->engine->sendDue($this->at('2026-10-26 08:15'));

        $this->engine->skipToday($this->recipient($reminder, $user), $this->at('2026-10-26 09:00'));

        $this->assertEquals($this->at('2026-10-27 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_an_unanswered_day_is_logged_as_missed_and_replaced(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1, 3], '2026-10-26 07:00');
        $this->engine->sendDue($this->at('2026-10-26 08:15'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-28 08:15')]);

        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-28 08:15')));

        $this->assertSame(1, $reminder->events()->where('type', ReminderEvent::MISSED)->count());
        $this->assertEquals($this->at('2026-10-28 08:15'), $this->recipient($reminder, $user)->pending_since);
    }

    public function test_a_pending_reminder_stays_on_the_due_list_after_work_hours(): void
    {
        $user = User::factory()->create();
        $reminder = $this->weekly($user, [1], '2026-10-26 07:00');
        $this->engine->sendDue($this->at('2026-10-26 08:15'));

        $this->assertCount(1, $this->engine->dueNow($user, $this->at('2026-10-26 20:00')));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 20:01'));

        $this->assertCount(0, $this->engine->dueNow($user, $this->at('2026-10-27 09:00')));
    }

    public function test_monthly_days_and_short_months(): void
    {
        $user = User::factory()->create();

        $reminder = Reminder::factory()->onMonthDays([15, -1])->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 18:00'));
        $this->assertEquals($this->at('2026-10-31 08:15'), $this->recipient($reminder, $user)->next_notify_at, 'the last day of October');

        $thirty = Reminder::factory()->onMonthDays([31])->create(['created_by' => $user->id]);
        $this->engine->sync($thirty, $this->at('2027-02-10 07:00'));
        $this->assertEquals($this->at('2027-02-28 08:15'), $this->recipient($thirty, $user)->next_notify_at, 'the 31st falls on the last day of February');

        $this->engine->done($this->recipient($thirty, $user), $this->at('2027-02-28 09:00'));
        $this->assertEquals($this->at('2027-03-31 08:15'), $this->recipient($thirty, $user)->next_notify_at);
    }

    public function test_time_zones_are_per_person(): void
    {
        $london = User::factory()->create(['timezone' => 'Europe/London']);
        $reminder = Reminder::factory()->onWeekdays([1])->create(['created_by' => $london->id]);

        $this->engine->sync($reminder, CarbonImmutable::parse('2026-10-26 06:00', 'Europe/London')->utc());

        $this->assertEquals(CarbonImmutable::parse('2026-10-26 08:15', 'Europe/London')->utc(), $this->recipient($reminder, $london)->next_notify_at);
    }

    public function test_the_schedule_in_words(): void
    {
        $this->assertSame('Every Mon, Wed, Fri', Reminder::factory()->onWeekdays([5, 1, 3])->make()->scheduleLabel());
        $this->assertSame('Every day', Reminder::factory()->onWeekdays([1, 2, 3, 4, 5, 6, 7])->make()->scheduleLabel());
        $this->assertSame('Monthly on the 1st, 22nd and last day', Reminder::factory()->onMonthDays([-1, 22, 1])->make()->scheduleLabel());
        $this->assertSame('Monthly on the 11th', Reminder::factory()->onMonthDays([11])->make()->scheduleLabel());
    }

    public function test_creating_one_in_the_app(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Check the weekly report', 'type' => 'repeating', 'recurrence' => 'weekly', 'frequency' => 'daily', 'weekdays' => [5, 1, 1],
        ])->assertRedirect(route('reminders.index'));

        $reminder = Reminder::query()->firstOrFail();
        $this->assertSame('1,5', $reminder->weekdays);
        $this->assertNull($reminder->month_days);
        $this->assertNull($reminder->interval_minutes);
        $this->assertCount(1, $reminder->recipients);
    }

    public function test_the_form_rejects_a_schedule_without_days(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'repeating', 'recurrence' => 'weekly', 'frequency' => 'daily'])
            ->assertSessionHasErrors('weekdays');
        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'repeating', 'recurrence' => 'monthly', 'frequency' => 'daily', 'month_days' => [0]])
            ->assertSessionHasErrors('month_days.0');
        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'repeating', 'frequency' => 'interval'])
            ->assertSessionHasErrors('interval_minutes');
    }

    public function test_changing_the_days_reschedules(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([1])->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 18:00'));
        $before = $this->recipient($reminder, $user)->next_notify_at;

        $this->actingAs($user)->patch(route('reminders.update', $reminder), [
            'title' => $reminder->title, 'type' => 'repeating', 'recurrence' => 'weekly', 'frequency' => 'daily', 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        ])->assertRedirect();

        $this->assertNotEquals($before, $this->recipient($reminder->fresh(), $user)->next_notify_at);
        $this->assertSame('1,2,3,4,5,6,7', $reminder->fresh()->weekdays);
    }

    public function test_the_schedule_fields_of_other_kinds_do_not_get_in_the_way(): void
    {
        $user = User::factory()->create();

        // The form always sends the day choice, also for a deadline: it must be ignored.
        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Deadline', 'type' => 'deadline', 'due_at' => now()->addDay()->format('Y-m-d\TH:i'), 'recurrence' => 'weekly', 'frequency' => 'daily',
        ])->assertSessionHasNoErrors();

        $reminder = Reminder::query()->firstOrFail();
        $this->assertNull($reminder->recurrence);
        $this->assertNull($reminder->weekdays);
    }

    public function test_the_form_and_list_render(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([2, 4])->create(['created_by' => $user->id, 'title' => 'Tuesday thing']);
        $this->engine->sync($reminder);

        $this->actingAs($user)->get(route('reminders.create'))->assertOk()->assertSee('Recurring')->assertSee('Chosen weekdays')->assertSee('Last day');
        $this->actingAs($user)->get(route('reminders.index'))->assertOk()->assertSee('Every Tue, Thu');
        $this->actingAs($user)->get(route('reminders.edit', $reminder))->assertOk();
    }

    public function test_every_work_day_once_a_day_follows_the_persons_own_work_days(): void
    {
        $user = User::factory()->create(['work_days' => '2,4']); // Tuesday and Thursday
        $reminder = Reminder::factory()->oncePerWorkday()->create(['created_by' => $user->id]);

        $this->engine->sync($reminder, $this->at('2026-10-26 07:00')); // a Monday

        $this->assertEquals($this->at('2026-10-27 08:15'), $this->recipient($reminder, $user)->next_notify_at);

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-27 10:00'));
        $this->assertEquals($this->at('2026-10-29 08:15'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame('Every work day', $reminder->scheduleLabel());
    }

    public function test_chosen_days_can_repeat_during_the_day(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([1, 3], 120, 2)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));

        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-26 08:15')));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 09:00'));
        $this->assertEquals($this->at('2026-10-26 11:00'), $this->recipient($reminder, $user)->next_notify_at, 'the timer rolls on 2 hours after Done');

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 11:05'));
        $this->assertEquals($this->at('2026-10-28 08:15'), $this->recipient($reminder, $user)->next_notify_at, 'the daily target of 2 is reached');
        $this->assertSame('Every Mon, Wed, every 120 min', $reminder->scheduleLabel());
    }

    public function test_a_repeat_that_would_land_after_work_hours_moves_to_the_next_chosen_day(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([1, 3], 120)->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));
        $this->engine->sendDue($this->at('2026-10-26 08:15'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 16:30'));

        $this->assertEquals($this->at('2026-10-28 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_the_old_repeating_reminder_is_unchanged(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->repeating(90)->create(['created_by' => $user->id]);

        $this->assertFalse($reminder->followsDays());
        $this->engine->sync($reminder, $this->at('2026-10-26 10:00'));
        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 10:00'));

        $this->assertEquals($this->at('2026-10-26 11:30'), $this->recipient($reminder, $user)->next_notify_at);
    }

    private function dueAt(User $user, string $time, int $lead = 240): Reminder
    {
        return Reminder::factory()->onWeekdays([1, 3])->create(['created_by' => $user->id, 'due_time' => $time, 'lead_minutes' => $lead]);
    }

    public function test_it_starts_reminding_before_the_due_time(): void
    {
        $user = User::factory()->create();
        $reminder = $this->dueAt($user, '15:00'); // 4 hours before: 11:00

        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));

        $this->assertEquals($this->at('2026-10-26 11:00'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame(0, $this->engine->sendDue($this->at('2026-10-26 10:59')));
        $this->assertSame(1, $this->engine->sendDue($this->at('2026-10-26 11:00')));
    }

    public function test_the_start_is_never_before_the_work_day_starts(): void
    {
        $user = User::factory()->create();
        $reminder = $this->dueAt($user, '09:00'); // 4 hours before would be 05:00

        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));

        $this->assertEquals($this->at('2026-10-26 08:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_it_speeds_up_towards_the_due_time_and_keeps_nagging_when_overdue(): void
    {
        $user = User::factory()->create();
        $reminder = $this->dueAt($user, '15:00', 480); // starts 08:15
        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));

        $this->assertSame(60, $this->engine->nagInterval($reminder, $this->at('2026-10-26 10:00'), $user)); // 5h left
        $this->assertSame(30, $this->engine->nagInterval($reminder, $this->at('2026-10-26 13:30'), $user)); // 90 min left
        $this->assertSame(10, $this->engine->nagInterval($reminder, $this->at('2026-10-26 14:45'), $user)); // 15 min left
        $this->assertSame(5, $this->engine->nagInterval($reminder, $this->at('2026-10-26 15:10'), $user)); // overdue

        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 15:10')]);
        $this->engine->sendDue($this->at('2026-10-26 15:10'));
        $this->assertEquals($this->at('2026-10-26 15:15'), $this->recipient($reminder, $user)->next_notify_at);
    }

    public function test_overdue_nagging_stops_when_the_work_day_ends(): void
    {
        $user = User::factory()->create();
        $reminder = $this->dueAt($user, '15:00');
        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));
        $this->recipient($reminder, $user)->update(['next_notify_at' => $this->at('2026-10-26 17:28')]);

        $this->engine->sendDue($this->at('2026-10-26 17:28'));

        $this->assertEquals($this->at('2026-10-28 11:00'), $this->recipient($reminder, $user)->next_notify_at, 'the next chosen day, 4 hours before 15:00');
        $this->assertNotNull($this->recipient($reminder, $user)->pending_since);
    }

    public function test_done_returns_on_the_next_chosen_day_before_the_due_time(): void
    {
        $user = User::factory()->create();
        $reminder = $this->dueAt($user, '15:00');
        $this->engine->sync($reminder, $this->at('2026-10-26 07:00'));
        $this->engine->sendDue($this->at('2026-10-26 11:00'));

        $this->engine->done($this->recipient($reminder, $user), $this->at('2026-10-26 12:00'));

        $this->assertEquals($this->at('2026-10-28 11:00'), $this->recipient($reminder, $user)->next_notify_at);
        $this->assertSame('Every Mon, Wed, due 15:00', $reminder->scheduleLabel());
    }

    public function test_created_in_the_app_with_a_due_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Weekly report', 'type' => 'repeating', 'recurrence' => 'weekly', 'frequency' => 'daily', 'weekdays' => [5],
            'due_time' => '15:00', 'recurring_lead_minutes' => 120,
        ])->assertRedirect(route('reminders.index'));

        $reminder = Reminder::query()->firstOrFail();
        $this->assertSame('15:00', $reminder->due_time);
        $this->assertSame(120, $reminder->lead_minutes);

        $this->actingAs($user)->post(route('reminders.store'), [
            'title' => 'Often', 'type' => 'repeating', 'frequency' => 'interval', 'interval_minutes' => 60, 'due_time' => '15:00',
        ])->assertRedirect();
        $this->assertNull(Reminder::query()->where('title', 'Often')->firstOrFail()->due_time, 'a due time only goes with once a day');

        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'Bad', 'type' => 'repeating', 'frequency' => 'daily', 'due_time' => 'soon'])
            ->assertSessionHasErrors('due_time');
    }
}
