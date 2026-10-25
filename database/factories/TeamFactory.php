<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'members_can_create' => false,
        ];
    }

    /**
     * Make the user the manager of the team (and a member of it).
     */
    public function managedBy(User $manager): static
    {
        return $this->state(fn () => ['manager_id' => $manager->getKey()])
            ->afterCreating(fn ($team) => $team->users()->syncWithoutDetaching([$manager->getKey()]));
    }
}
