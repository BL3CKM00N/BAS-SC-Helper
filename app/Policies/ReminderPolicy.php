<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;

/**
 * Personal reminders belong to their owner alone, not even admins see them.
 * Team reminders are visible to every team member.
 */
class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        if ($reminder->isPersonal()) {
            return $reminder->created_by === $user->getKey();
        }

        return $reminder->team->hasMember($user);
    }

    /**
     * May the user create a reminder for the team (or a personal one when there is no team)?
     */
    public function createFor(User $user, ?Team $team = null): bool
    {
        if ($team === null) {
            return true;
        }

        return $team->isManagedBy($user) || ($team->members_can_create && $team->hasMember($user));
    }

    public function update(User $user, Reminder $reminder): bool
    {
        if ($reminder->isPersonal()) {
            return $reminder->created_by === $user->getKey();
        }

        return $reminder->created_by === $user->getKey() && $reminder->team->hasMember($user)
            || $reminder->team->isManagedBy($user);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->update($user, $reminder);
    }
}
