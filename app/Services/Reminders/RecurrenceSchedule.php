<?php

namespace App\Services\Reminders;

use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * When a reminder "on chosen days" comes back, in the time zone of each person.
 *
 * The first notification of a chosen day goes out START_DELAY_MINUTES after that person's work day starts.
 * A chosen day counts even when it is not one of the person's work days: on such a day the person's work
 * hours (start to end) are used as the window in which the reminder may nag.
 */
final class RecurrenceSchedule
{
    public const START_DELAY_MINUTES = 15;

    /** How far ahead to look for the next chosen day. */
    private const LOOKAHEAD_DAYS = 400;

    /**
     * Does the reminder come back on this (local) day?
     */
    public static function matches(Reminder $reminder, CarbonInterface $localDay): bool
    {
        if ($reminder->recurrence === Reminder::MONTHLY) {
            $last = $localDay->daysInMonth;

            foreach ($reminder->monthDayList() as $day) {
                // The 31st in a month with 30 days (or the 30th in February) falls on the last day.
                $target = $day === -1 ? $last : min($day, $last);

                if ($localDay->day === $target) {
                    return true;
                }
            }

            return false;
        }

        return in_array($localDay->dayOfWeekIso, $reminder->weekdayList(), true);
    }

    /**
     * The first notification of the given local day.
     */
    public static function startOn(User $user, CarbonImmutable $localDay): CarbonImmutable
    {
        return self::at($localDay, $user->work_start)->addMinutes(self::START_DELAY_MINUTES);
    }

    /**
     * Is this moment inside the window in which the reminder may notify: a chosen day, between work start and end.
     */
    public static function isActive(Reminder $reminder, User $user, CarbonInterface $at): bool
    {
        $local = self::local($user, $at);

        return self::matches($reminder, $local)
            && $local >= self::at($local, $user->work_start)
            && $local < self::at($local, $user->work_end);
    }

    /**
     * Where a brand new reminder starts: today's notification when today is a chosen day and it is not over yet
     * (right away when the start time already passed), otherwise the next chosen day.
     */
    public static function current(Reminder $reminder, User $user, CarbonInterface $now): CarbonImmutable
    {
        $local = self::local($user, $now);

        if (self::matches($reminder, $local) && $local < self::at($local, $user->work_end)) {
            return max(self::startOn($user, $local), $local)->utc();
        }

        return self::nextAfterToday($reminder, $user, $now);
    }

    /**
     * The first notification on a chosen day after today. Used after Done and Skip.
     */
    public static function nextAfterToday(Reminder $reminder, User $user, CarbonInterface $from): CarbonImmutable
    {
        $local = self::local($user, $from)->startOfDay();

        for ($i = 1; $i <= self::LOOKAHEAD_DAYS; $i++) {
            $day = $local->addDays($i);

            if (self::matches($reminder, $day)) {
                return self::startOn($user, $day)->utc();
            }
        }

        return $local->addDay()->utc();
    }

    /**
     * When the next nag may go out if it was planned for the given moment: as planned inside the window,
     * the first notification of the day when the window has not opened yet, otherwise the next chosen day.
     */
    public static function nextNag(Reminder $reminder, User $user, CarbonInterface $at): CarbonImmutable
    {
        if (self::isActive($reminder, $user, $at)) {
            return CarbonImmutable::instance($at);
        }

        $local = self::local($user, $at);

        if (self::matches($reminder, $local) && $local < self::at($local, $user->work_start)) {
            return self::startOn($user, $local)->utc();
        }

        return self::nextAfterToday($reminder, $user, $at);
    }

    private static function local(User $user, CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(self::timezone($user));
    }

    private static function timezone(User $user): string
    {
        try {
            new DateTimeZone((string) $user->timezone);

            return (string) $user->timezone;
        } catch (Throwable) {
            return 'Europe/Amsterdam';
        }
    }

    private static function at(CarbonImmutable $day, ?string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time ?: '00:00') + [1 => 0]);

        return $day->setTime($hour, $minute);
    }
}
