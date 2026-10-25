<?php

namespace App\Services\Admin;

use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountInvite;
use App\Services\Account\UserSessions;
use App\Services\Teams\TeamManagement;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What an admin can do to somebody else's account, with the safeguards that keep the app reachable.
 */
class AccountAdmin
{
    public function __construct(
        private readonly UserSessions $sessions,
        private readonly TeamManagement $teams,
    ) {}

    /**
     * True when this person is the only admin who can still sign in.
     */
    public function isLastAdmin(User $user): bool
    {
        if (! $user->isAdmin() || $user->isDisabled()) {
            return false;
        }

        return ! User::query()
            ->whereKeyNot($user->getKey())
            ->whereNull('disabled_at')
            ->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))
            ->exists();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function disable(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new InvalidArgumentException('You cannot disable your own account.');
        }

        $this->ensureNotLastAdmin($user, 'disabled');

        $user->forceFill(['disabled_at' => now()])->save();
        $this->signOutEverywhere($user, $actor, false);

        Audit::record('user.disabled', 'Disabled the account of '.$user->name, $user, actor: $actor);
    }

    public function enable(User $user, User $actor): void
    {
        $user->forceFill(['disabled_at' => null])->save();

        Audit::record('user.enabled', 'Enabled the account of '.$user->name, $user, actor: $actor);
    }

    /**
     * Give the person a new temporary password, to be handed over by the admin. They must change it at once.
     */
    public function resetPassword(User $user, User $actor): string
    {
        $temporary = Str::password(14, symbols: false);

        $user->forceFill(['password' => $temporary, 'must_change_password' => true, 'invited_at' => null])->save();
        $this->signOutEverywhere($user, $actor, false);

        Audit::record('user.password_reset', 'Reset the password of '.$user->name, $user, actor: $actor);

        return $temporary;
    }

    public function signOutEverywhere(User $user, User $actor, bool $log = true): void
    {
        $this->sessions->revokeAll($user);
        $this->sessions->rotateRememberToken($user);

        if ($log) {
            Audit::record('user.signed_out', 'Signed '.$user->name.' out on all devices', $user, actor: $actor);
        }
    }

    /**
     * @throws InvalidArgumentException when the person was not invited by email or mail cannot be sent
     */
    public function resendInvite(User $user, User $actor): void
    {
        if ($user->invited_at === null) {
            throw new InvalidArgumentException($user->name.' has no open invitation.');
        }

        if (! MailSettings::canDeliver()) {
            throw new InvalidArgumentException('The app cannot send email yet.');
        }

        $user->forceFill(['invited_at' => now()])->save();
        $user->notify(new AccountInvite($actor));

        Audit::record('user.invite_resent', 'Sent a new invitation to '.$user->email, $user, actor: $actor);
    }

    /**
     * Remove a person's data but keep the account row, so team reminders and the audit log keep making sense.
     * They are taken out of every team, lose their devices and personal reminders, and can never sign in again.
     *
     * @throws InvalidArgumentException
     */
    public function anonymise(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new InvalidArgumentException('You cannot remove your own account.');
        }

        $this->ensureNotLastAdmin($user, 'removed');

        $label = $user->name;

        DB::transaction(function () use ($user) {
            $this->teams->openTeamOf($user);
            $user->teams()->detach();

            ReminderRecipient::query()->where('user_id', $user->getKey())->delete();
            Reminder::query()->whereNull('team_id')->where(fn ($query) => $query->where('created_by', $user->getKey())->orWhere('assignee_id', $user->getKey()))->delete();
            $user->pushSubscriptions()->delete();

            $user->forceFill([
                'name' => 'Former user',
                'email' => 'removed-'.$user->getKey().'@removed.invalid',
                'password' => Str::random(48),
                'role_id' => Role::query()->where('name', Role::USER)->value('id'),
                'must_change_password' => false,
                'invited_at' => null,
                'disabled_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $this->sessions->revokeAll($user);
            $this->teams->pruneAbandoned();
        });

        Audit::record('user.anonymised', 'Removed the personal data of '.$label, $user, actor: $actor);
    }

    private function ensureNotLastAdmin(User $user, string $what): void
    {
        if ($this->isLastAdmin($user)) {
            throw new InvalidArgumentException($user->name.' is the only admin who can still sign in, so the account cannot be '.$what.'.');
        }
    }
}
