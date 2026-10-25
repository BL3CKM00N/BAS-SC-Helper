<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only admins reach the admin panel (see User::canAccessPanel), so inside it the only
 * restriction is that nobody can delete their own account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
