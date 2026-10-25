<?php

namespace App\Services\Reminders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * A user's working days and hours, evaluated in their own time zone. All moments are
 * returned in UTC, like the rest of the app stores them.
 */
final class WorkHours
{
    public static function isWorkingTime(User $user, CarbonInterface $at): bool
    {
        $days = self::days($user);

        if ($days === []) {
            return true;
        }

        $local = self::local($user, $at);

        return in_array($local->dayOfWeekIso, $days, true)
            && $local >= self::at($local, $user->work_start)
            && $local < self::at($local, $user->work_end);
    }

    /**
     * The given moment when it falls inside working time, otherwise the start of the next working period.
     */
    public static function nextWorkingTime(User $user, CarbonInterface $from): CarbonImmutable
    {
        $days = self::days($user);
        $local = self::local($user, $from);

        if ($days === []) {
            return $local->utc();
        }

        for ($i = 0; $i <= 14; $i++) {
            $day = $local->addDays($i)->startOfDay();

            if (! in_array($day->dayOfWeekIso, $days, true)) {
                continue;
            }

            $start = self::at($day, $user->work_start);
            $end = self::at($day, $user->work_end);

            if ($i === 0) {
                if ($local < $start) {
                    return $start->utc();
                }

                if ($local < $end) {
                    return $local->utc();
                }

                continue;
            }

            return $start->utc();
        }

        return $local->utc();
    }

    /**
     * The start of the working day after the one that contains the given moment.
     */
    public static function startOfNextWorkday(User $user, CarbonInterface $from): CarbonImmutable
    {
        $days = self::days($user);
        $local = self::local($user, $from);

        for ($i = 1; $i <= 14; $i++) {
            $day = $local->addDays($i)->startOfDay();

            if ($days === [] || in_array($day->dayOfWeekIso, $days, true)) {
                return self::at($day, $user->work_start)->utc();
            }
        }

        return $local->addDay()->utc();
    }

    /**
     * Midnight at the start of the user's current day.
     */
    public static function startOfToday(User $user, CarbonInterface $at): CarbonImmutable
    {
        return self::local($user, $at)->startOfDay()->utc();
    }

    /**
     * @return list<int>
     */
    private static function days(User $user): array
    {
        return array_values(array_filter(
            array_map('intval', explode(',', (string) $user->work_days)),
            fn (int $day) => $day >= 1 && $day <= 7,
        ));
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
