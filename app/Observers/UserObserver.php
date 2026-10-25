<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\User;
use App\Services\Teams\TeamManagement;

/**
 * Keeps teams in step with the team manager role: getting the role creates a team, losing it opens the team.
 */
class UserObserver
{
    public function __construct(private readonly TeamManagement $teams) {}

    public function created(User $user): void
    {
        if ($user->isTeamManager()) {
            $this->teams->ensureTeamFor($user);
        }
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('role_id')) {
            return;
        }

        $wasManager = $user->getOriginal('role_id') !== null
            && Role::query()->whereKey($user->getOriginal('role_id'))->value('name') === Role::TEAM_MANAGER;

        $user->unsetRelation('role');

        if ($user->isTeamManager() && ! $wasManager) {
            $this->teams->ensureTeamFor($user);
        } elseif ($wasManager && ! $user->isTeamManager()) {
            $this->teams->openTeamOf($user);
        }
    }

    public function deleted(User $user): void
    {
        // The foreign key already emptied manager_id, so the team may now be open and abandoned.
        $this->teams->pruneAbandoned();
    }
}
