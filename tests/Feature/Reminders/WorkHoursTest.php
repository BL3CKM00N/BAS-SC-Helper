<?php

namespace Tests\Feature\Reminders;

use App\Models\User;
use App\Services\Reminders\WorkHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkHoursTest extends TestCase
{
    use RefreshDatabase;

    private function amsterdam(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Europe/Amsterdam')->utc();
    }

    public function test_it_knows_when_the_user_is_working(): void
    {
        $user = User::factory()->create(); // Mon-Fri 08:00-17:30, Europe/Amsterdam

        $this->assertTrue(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-26 08:00')));
        $this->assertTrue(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-26 17:29')));
        $this->assertFalse(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-26 17:30')));
        $this->assertFalse(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-26 07:59')));
        $this->assertFalse(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-25 12:00'))); // Sunday
    }

    public function test_it_moves_a_moment_into_the_next_working_period(): void
    {
        $user = User::factory()->create();

        $this->assertEquals($this->amsterdam('2026-10-26 10:00'), WorkHours::nextWorkingTime($user, $this->amsterdam('2026-10-26 10:00')));
        $this->assertEquals($this->amsterdam('2026-10-26 08:00'), WorkHours::nextWorkingTime($user, $this->amsterdam('2026-10-26 06:00')));
        $this->assertEquals($this->amsterdam('2026-10-27 08:00'), WorkHours::nextWorkingTime($user, $this->amsterdam('2026-10-26 18:00')));
        $this->assertEquals($this->amsterdam('2026-10-26 08:00'), WorkHours::nextWorkingTime($user, $this->amsterdam('2026-10-25 12:00'))); // Sunday
        $this->assertEquals($this->amsterdam('2026-10-26 08:00'), WorkHours::nextWorkingTime($user, $this->amsterdam('2026-10-23 18:00'))); // Friday evening
    }

    public function test_the_next_working_day_skips_the_weekend(): void
    {
        $user = User::factory()->create();

        $this->assertEquals($this->amsterdam('2026-10-27 08:00'), WorkHours::startOfNextWorkday($user, $this->amsterdam('2026-10-26 10:00')));
        $this->assertEquals($this->amsterdam('2026-10-26 08:00'), WorkHours::startOfNextWorkday($user, $this->amsterdam('2026-10-23 10:00'))); // Friday
    }

    public function test_it_uses_the_users_own_hours_days_and_time_zone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/New_York', 'work_days' => '1,3', 'work_start' => '09:30', 'work_end' => '12:00']);

        $monday = CarbonImmutable::parse('2026-10-26 10:00', 'America/New_York')->utc();
        $tuesday = CarbonImmutable::parse('2026-10-27 10:00', 'America/New_York')->utc();

        $this->assertTrue(WorkHours::isWorkingTime($user, $monday));
        $this->assertFalse(WorkHours::isWorkingTime($user, $tuesday));
        $this->assertEquals(CarbonImmutable::parse('2026-10-28 09:30', 'America/New_York')->utc(), WorkHours::nextWorkingTime($user, $tuesday));
    }

    public function test_the_start_of_today_follows_the_users_time_zone(): void
    {
        $user = User::factory()->create();

        $this->assertEquals($this->amsterdam('2026-10-26 00:00'), WorkHours::startOfToday($user, $this->amsterdam('2026-10-26 23:30')));
    }

    public function test_a_user_without_work_days_is_always_available(): void
    {
        $user = User::factory()->create(['work_days' => '']);
        $sunday = $this->amsterdam('2026-10-25 03:00');

        $this->assertTrue(WorkHours::isWorkingTime($user, $sunday));
        $this->assertEquals($sunday, WorkHours::nextWorkingTime($user, $sunday));
    }

    public function test_an_invalid_time_zone_falls_back_to_amsterdam(): void
    {
        $user = User::factory()->create(['timezone' => 'Nowhere/Land']);

        $this->assertTrue(WorkHours::isWorkingTime($user, $this->amsterdam('2026-10-26 10:00')));
    }
}
