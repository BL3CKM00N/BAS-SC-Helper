<?php

namespace App\Observers;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\Audit;
use App\Services\Teams\TeamManagement;

/**
 * Keeps teams in step with the team manager role: getting the role creates a team, losing it opens the team.
 */
class UserObserver
{
    public function __construct(private readonly TeamManagement $teams) {}

    public function created(User $user): void
    {
        Audit::record('user.created', 'Created the account of '.$user->name.' ('.$user->email.')', $user);

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

        Audit::record('user.role_changed', 'Changed the role of '.$user->name.' to '.($user->role?->name ?? 'none'), $user);

        if ($user->isTeamManager() && ! $wasManager) {
            $this->teams->ensureTeamFor($user);
        } elseif ($wasManager && ! $user->isTeamManager()) {
            $this->teams->openTeamOf($user);
        }
    }

    public function deleted(User $user): void
    {
        Audit::record('user.deleted', 'Deleted the account of '.$user->name.' ('.$user->email.')', null, ['user_id' => $user->getKey()]);

        // The foreign key already emptied manager_id, so the team may now be open and abandoned.
        $this->teams->pruneAbandoned();
    }
}
