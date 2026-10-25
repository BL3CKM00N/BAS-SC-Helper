<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'type' => Reminder::DEADLINE,
            'due_at' => now()->addDay(),
            'completion_mode' => Reminder::ANYONE,
        ];
    }

    public function repeating(int $everyMinutes = 60, ?int $dailyTarget = null): static
    {
        return $this->state(fn () => [
            'type' => Reminder::REPEATING,
            'due_at' => null,
            'interval_minutes' => $everyMinutes,
            'daily_target' => $dailyTarget,
        ]);
    }
}
