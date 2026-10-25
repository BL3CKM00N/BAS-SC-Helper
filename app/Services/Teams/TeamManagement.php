<?php

namespace App\Services\Teams;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The rules about who manages a team and when a team exists.
 *
 * A team has at most one manager and a manager manages at most one team. A team without a manager is
 * "open". A team that is open and has no members left is removed.
 */
class TeamManagement
{
    /**
     * Give a new team manager their own team (or return the one they already manage).
     */
    public function ensureTeamFor(User $user): Team
    {
        $existing = $user->managedTeam()->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user) {
            $team = Team::query()->create(['name' => $this->freeName($user->name."'s team"), 'manager_id' => $user->getKey()]);
            $team->users()->attach($user);

            return $team;
        });
    }

    /**
     * The manager lost the role: their team has no manager any more.
     */
    public function openTeamOf(User $user): void
    {
        Team::query()->where('manager_id', $user->getKey())->update(['manager_id' => null]);

        $this->pruneAbandoned();
    }

    /**
     * Let a person take over an open team.
     *
     * @throws InvalidArgumentException when the team already has a manager or the user manages another team
     */
    public function assignManager(Team $team, User $user): void
    {
        if (! $team->isOpen()) {
            throw new InvalidArgumentException($team->name.' already has a manager.');
        }

        if ($user->managedTeam()->exists()) {
            throw new InvalidArgumentException($user->name.' already manages another team.');
        }

        DB::transaction(function () use ($team, $user) {
            $team->update(['manager_id' => $user->getKey()]);
            $team->users()->syncWithoutDetaching([$user->getKey()]);

            // Admins keep their role, everyone else becomes a team manager. The team already exists,
            // so the role change does not create a second one.
            if (! $user->isAdmin() && ! $user->isTeamManager()) {
                $user->update(['role_id' => Role::query()->where('name', Role::TEAM_MANAGER)->value('id')]);
            }
        });
    }

    public function addMember(Team $team, User $user): void
    {
        $team->users()->syncWithoutDetaching([$user->getKey()]);
    }

    /**
     * Take someone out of a team. The manager cannot be removed from their own team.
     *
     * @throws InvalidArgumentException
     */
    public function removeMember(Team $team, User $user): void
    {
        if ($team->isManagedBy($user)) {
            throw new InvalidArgumentException($user->name.' manages this team and cannot be removed from it.');
        }

        $team->users()->detach($user);

        $this->pruneAbandoned();
    }

    /**
     * Delete open teams that nobody is in any more. Returns how many were removed.
     */
    public function pruneAbandoned(): int
    {
        $removed = 0;

        Team::query()->whereNull('manager_id')->whereDoesntHave('users')->each(function (Team $team) use (&$removed) {
            $team->delete();
            $removed++;
        });

        return $removed;
    }

    private function freeName(string $name): string
    {
        $candidate = $name;

        for ($i = 2; Team::query()->where('name', $candidate)->exists(); $i++) {
            $candidate = $name.' '.$i;
        }

        return $candidate;
    }
}
