<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamRequest>
 */
class TeamRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'requested_by' => User::factory(),
            'type' => TeamRequest::NEW_MEMBER,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => TeamRequest::PENDING,
        ];
    }
}
