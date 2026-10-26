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
            'title' => 'Check the weekly report', 'type' => 'scheduled', 'recurrence' => 'weekly', 'weekdays' => [5, 1, 1],
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

        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'scheduled', 'recurrence' => 'weekly'])
            ->assertSessionHasErrors('weekdays');
        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'scheduled', 'recurrence' => 'monthly', 'month_days' => [0]])
            ->assertSessionHasErrors('month_days.0');
        $this->actingAs($user)->post(route('reminders.store'), ['title' => 'x', 'type' => 'scheduled'])
            ->assertSessionHasErrors('recurrence');
    }

    public function test_changing_the_days_reschedules(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([1])->create(['created_by' => $user->id]);
        $this->engine->sync($reminder, $this->at('2026-10-26 18:00'));
        $before = $this->recipient($reminder, $user)->next_notify_at;

        $this->actingAs($user)->patch(route('reminders.update', $reminder), [
            'title' => $reminder->title, 'type' => 'scheduled', 'recurrence' => 'weekly', 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
        ])->assertRedirect();

        $this->assertNotEquals($before, $this->recipient($reminder->fresh(), $user)->next_notify_at);
        $this->assertSame('1,2,3,4,5,6,7', $reminder->fresh()->weekdays);
    }

    public function test_the_form_and_list_render(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->onWeekdays([2, 4])->create(['created_by' => $user->id, 'title' => 'Tuesday thing']);
        $this->engine->sync($reminder);

        $this->actingAs($user)->get(route('reminders.create'))->assertOk()->assertSee('On chosen days')->assertSee('Last day');
        $this->actingAs($user)->get(route('reminders.index'))->assertOk()->assertSee('Every Tue, Thu');
        $this->actingAs($user)->get(route('reminders.edit', $reminder))->assertOk();
    }
}
