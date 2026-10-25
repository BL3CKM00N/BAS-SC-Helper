<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * App admins manage teams in the Filament panel. Inside the app a team is visible to its
 * members and managed by its managers.
 */
class TeamPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Team $team): bool
    {
        return $team->hasMember($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Team $team): bool
    {
        return $team->isManagedBy($user);
    }

    /**
     * Sending requests to add, remove or create team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $team->isManagedBy($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
