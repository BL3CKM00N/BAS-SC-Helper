<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\ReminderEvent;
use App\Models\ReminderRecipient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decides who gets a reminder, when the next one is due and what happens on Done, Snooze and Skip.
 *
 * Every recipient has their own `next_notify_at`. A recipient is "pending" once a notification went out
 * and stays pending (and shown in the "due now" list) until they act on it.
 */
class ReminderEngine
{
    /** Minutes between nags for a repeating reminder that was ignored. */
    public const DEFAULT_REPEAT_NAG = 10;

    public function __construct(private readonly ReminderNotifier $notifier) {}

    // ---------------------------------------------------------------- recipients

    /**
     * @return Collection<int, User>
     */
    public function usersFor(Reminder $reminder): Collection
    {
        if ($reminder->isPersonal()) {
            return User::query()->whereKey($reminder->created_by)->get();
        }

        if ($reminder->assignee_id !== null) {
            return $reminder->team->users()->whereKey($reminder->assignee_id)->get();
        }

        return $reminder->team->users()->get();
    }

    /**
     * Make the recipients match who should get the reminder now. New recipients get their first
     * notification time, people who are no longer in the team lose the reminder.
     */
    public function sync(Reminder $reminder, ?CarbonInterface $now = null): void
    {
        $now = $this->now($now);
        $users = $this->usersFor($reminder);

        $reminder->recipients()->whereNotIn('user_id', $users->modelKeys())->delete();

        foreach ($users as $user) {
            $recipient = $reminder->recipients()->firstOrNew(['user_id' => $user->getKey()]);

            if (! $recipient->exists) {
                $recipient->next_notify_at = $this->firstNotification($reminder, $user, $now);
                $recipient->save();
            }
        }
    }

    /**
     * Start over after the schedule of a reminder was edited.
     */
    public function reschedule(Reminder $reminder, ?CarbonInterface $now = null): void
    {
        $reminder->recipients()->delete();
        $reminder->forceFill(['completed_at' => null])->save();

        $this->sync($reminder, $now);
    }

    public function syncTeamReminders(?CarbonInterface $now = null): void
    {
        Reminder::query()
            ->whereNotNull('team_id')
            ->whereNull('assignee_id')
            ->whereNull('archived_at')
            ->whereNull('completed_at')
            ->with('team')
            ->each(fn (Reminder $reminder) => $this->sync($reminder, $now));
    }

    // ---------------------------------------------------------------- timing

    public function firstNotification(Reminder $reminder, User $user, CarbonImmutable $now): CarbonImmutable
    {
        if ($reminder->isScheduled()) {
            return RecurrenceSchedule::current($reminder, $user, $now);
        }

        $at = $now;

        if ($reminder->isDeadline() && $reminder->due_at) {
            $start = CarbonImmutable::instance($reminder->due_at)->subMinutes($reminder->lead_minutes);
            $at = $start->greaterThan($now) ? $start : $now;
        }

        return $this->shift($reminder, $user, $at);
    }

    /**
     * Minutes until the next nag: faster the closer the deadline gets.
     */
    public function nagInterval(Reminder $reminder, CarbonInterface $now): int
    {
        if ($reminder->nag_minutes) {
            return $reminder->nag_minutes;
        }

        if ($reminder->isRepeating() || ! $reminder->due_at) {
            return self::DEFAULT_REPEAT_NAG;
        }

        $minutesLeft = $now->diffInMinutes($reminder->due_at, false);

        return match (true) {
            $minutesLeft > 120 => 60,
            $minutesLeft > 30 => 30,
            $minutesLeft > 0 => 10,
            default => 5,
        };
    }

    /**
     * Move a moment into the user's work hours, unless this is an overdue deadline that may ignore them.
     */
    public function shift(Reminder $reminder, User $user, CarbonImmutable $at): CarbonImmutable
    {
        if ($reminder->isScheduled()) {
            return RecurrenceSchedule::nextNag($reminder, $user, $at);
        }

        if ($this->ignoresWorkHours($reminder, $at)) {
            return $at;
        }

        return WorkHours::nextWorkingTime($user, $at);
    }

    private function ignoresWorkHours(Reminder $reminder, CarbonInterface $at): bool
    {
        return $reminder->isDeadline()
            && $reminder->ignore_work_hours_when_overdue
            && $reminder->due_at !== null
            && $reminder->due_at->lessThanOrEqualTo($at);
    }

    // ---------------------------------------------------------------- sending

    /**
     * Notify everyone whose time has come. Called every minute. Returns the number of notifications.
     */
    public function sendDue(?CarbonInterface $now = null): int
    {
        $now = $this->now($now);

        $this->syncTeamReminders($now);

        $sent = 0;

        ReminderRecipient::query()
            ->whereNull('completed_at')
            ->whereNotNull('next_notify_at')
            ->where('next_notify_at', '<=', $now)
            ->whereHas('user', fn ($query) => $query->whereNull('disabled_at'))
            ->whereHas('reminder', fn ($query) => $query->whereNull('archived_at')->whereNull('completed_at'))
            ->with(['reminder.team', 'user'])
            ->each(function (ReminderRecipient $recipient) use ($now, &$sent) {
                $reminder = $recipient->reminder;
                $user = $recipient->user;

                if ($reminder->isScheduled()) {
                    // A new chosen day replaces a notification that was never answered.
                    if ($recipient->pending_since && ! $this->sameLocalDay($user, $recipient->pending_since, $now)) {
                        $this->log($reminder, $user, ReminderEvent::MISSED, $now, ['since' => $recipient->pending_since->toIso8601String()]);
                        $recipient->pending_since = null;
                    }

                    $allowed = RecurrenceSchedule::isActive($reminder, $user, $now);
                    $postpone = fn () => RecurrenceSchedule::nextNag($reminder, $user, $now);
                } else {
                    $allowed = WorkHours::isWorkingTime($user, $now) || $this->ignoresWorkHours($reminder, $now);
                    $postpone = fn () => WorkHours::nextWorkingTime($user, $now);
                }

                if (! $allowed) {
                    $recipient->forceFill(['next_notify_at' => $postpone()])->save();

                    return;
                }

                $recipient->forceFill([
                    'pending_since' => $recipient->pending_since ?? $now,
                    'last_notified_at' => $now,
                    'next_notify_at' => $this->shift($reminder, $user, $now->addMinutes($this->nagInterval($reminder, $now))),
                ])->save();

                $this->log($reminder, $user, ReminderEvent::NOTIFIED, $now);
                $this->notifier->send($recipient);
                $sent++;
            });

        return $sent;
    }

    // ---------------------------------------------------------------- actions

    public function done(ReminderRecipient $recipient, ?CarbonInterface $now = null): void
    {
        $now = $this->now($now);
        $reminder = $recipient->reminder()->with('team')->first();

        DB::transaction(function () use ($recipient, $reminder, $now) {
            $this->log($reminder, $recipient->user, ReminderEvent::DONE, $now);

            $everyone = $reminder->completion_mode === Reminder::ANYONE;
            $targets = $everyone ? $reminder->recipients()->with('user')->get() : collect([$recipient]);

            if ($reminder->isDeadline()) {
                foreach ($targets as $target) {
                    $target->forceFill(['completed_at' => $now, 'pending_since' => null, 'next_notify_at' => null])->save();
                }

                if ($everyone || $reminder->recipients()->whereNull('completed_at')->doesntExist()) {
                    $reminder->forceFill(['completed_at' => $now])->save();
                }

                return;
            }

            foreach ($targets as $target) {
                $this->scheduleNextRepetition($reminder, $target, $now);
            }
        });
    }

    public function snooze(ReminderRecipient $recipient, int $minutes, ?CarbonInterface $now = null): void
    {
        $now = $this->now($now);
        $reminder = $recipient->reminder;
        $until = $now->addMinutes($minutes);

        $recipient->forceFill([
            'snoozed_until' => $until,
            'skipped_until' => null,
            'pending_since' => null,
            'next_notify_at' => $this->shift($reminder, $recipient->user, $until),
        ])->save();

        $this->log($reminder, $recipient->user, ReminderEvent::SNOOZED, $now, ['minutes' => $minutes]);
    }

    /**
     * Hide the reminder until the next working day.
     */
    public function skipToday(ReminderRecipient $recipient, ?CarbonInterface $now = null): void
    {
        $now = $this->now($now);
        $until = $recipient->reminder->isScheduled()
            ? RecurrenceSchedule::nextAfterToday($recipient->reminder, $recipient->user, $now)
            : WorkHours::startOfNextWorkday($recipient->user, $now);

        $recipient->forceFill([
            'skipped_until' => $until,
            'snoozed_until' => null,
            'pending_since' => null,
            'next_notify_at' => $until,
        ])->save();

        $this->log($recipient->reminder, $recipient->user, ReminderEvent::SKIPPED, $now);
    }

    private function scheduleNextRepetition(Reminder $reminder, ReminderRecipient $recipient, CarbonImmutable $now): void
    {
        $user = $recipient->user;

        if ($reminder->isScheduled()) {
            $recipient->forceFill([
                'pending_since' => null,
                'snoozed_until' => null,
                'skipped_until' => null,
                'next_notify_at' => RecurrenceSchedule::nextAfterToday($reminder, $user, $now),
            ])->save();

            return;
        }

        $targetReached = $reminder->daily_target
            && $this->doneToday($reminder, $user, $now) >= $reminder->daily_target;

        $next = $targetReached
            ? WorkHours::startOfNextWorkday($user, $now)
            : $this->shift($reminder, $user, $now->addMinutes((int) $reminder->interval_minutes));

        $recipient->forceFill([
            'pending_since' => null,
            'snoozed_until' => null,
            'skipped_until' => null,
            'next_notify_at' => $next,
        ])->save();
    }

    // ---------------------------------------------------------------- reading

    /**
     * Repetitions done today. For a shared timer ("anyone") everyone's count is added up.
     */
    public function doneToday(Reminder $reminder, User $user, ?CarbonInterface $now = null): int
    {
        $now = $this->now($now);

        return $reminder->events()
            ->where('type', ReminderEvent::DONE)
            ->when($reminder->completion_mode === Reminder::EACH, fn ($query) => $query->where('user_id', $user->getKey()))
            ->where('created_at', '>=', WorkHours::startOfToday($user, $now))
            ->where('created_at', '<=', $now)
            ->count();
    }

    /**
     * What the user should act on right now.
     *
     * @return Collection<int, ReminderRecipient>
     */
    public function dueNow(User $user, ?CarbonInterface $now = null): Collection
    {
        $now = $this->now($now);

        return ReminderRecipient::query()
            ->where('user_id', $user->getKey())
            ->whereNull('completed_at')
            ->where(fn ($query) => $query->whereNotNull('pending_since')->orWhere('next_notify_at', '<=', $now))
            ->whereHas('reminder', fn ($query) => $query->whereNull('archived_at')->whereNull('completed_at'))
            ->with('reminder.team')
            ->get()
            ->filter(fn (ReminderRecipient $recipient) => $recipient->pending_since
                || ($recipient->reminder->isScheduled()
                    ? RecurrenceSchedule::isActive($recipient->reminder, $user, $now)
                    : WorkHours::isWorkingTime($user, $now) || $this->ignoresWorkHours($recipient->reminder, $now)))
            ->sortBy(fn (ReminderRecipient $recipient) => $recipient->pending_since ?? $recipient->next_notify_at)
            ->values();
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function log(Reminder $reminder, User $user, string $type, CarbonImmutable $at, ?array $meta = null): void
    {
        ReminderEvent::query()->create([
            'reminder_id' => $reminder->getKey(),
            'user_id' => $user->getKey(),
            'type' => $type,
            'meta' => $meta,
            'created_at' => $at,
        ]);
    }

    private function sameLocalDay(User $user, CarbonInterface $a, CarbonInterface $b): bool
    {
        return $user->local($a)?->toDateString() === $user->local($b)?->toDateString();
    }

    private function now(?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now());
    }
}
