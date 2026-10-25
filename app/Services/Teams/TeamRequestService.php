<?php

namespace App\Services\Teams;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use App\Notifications\AccountInvite;
use App\Notifications\TeamRequestDecided;
use App\Notifications\TeamRequestSubmitted;
use App\Support\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Team managers never change who is in a team themselves. They send a request and an admin approves or rejects it.
 */
class TeamRequestService
{
    public const DELIVER_PASSWORD = 'password';

    public const DELIVER_EMAIL = 'email';

    public function __construct(private readonly TeamManagement $teams) {}

    /**
     * @param  array{name?: ?string, email?: ?string, user_id?: int|string|null, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function submit(User $manager, Team $team, string $type, array $data): TeamRequest
    {
        $attributes = match ($type) {
            TeamRequest::NEW_MEMBER => $this->newMember($data),
            TeamRequest::ADD_MEMBER => $this->addMember($team, $data),
            TeamRequest::REMOVE_MEMBER => $this->removeMember($team, $data),
            default => throw ValidationException::withMessages(['type' => 'Unknown kind of request.']),
        };

        $request = TeamRequest::query()->create($attributes + [
            'team_id' => $team->getKey(),
            'requested_by' => $manager->getKey(),
            'type' => $type,
            'note' => $data['note'] ?? null,
            'status' => TeamRequest::PENDING,
        ]);

        $this->push(User::query()->whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))->get(), new TeamRequestSubmitted($request));

        return $request;
    }

    /**
     * Approve a request and carry it out.
     *
     * @param  string  $delivery  how a brand new account gets its password: "password" or "email"
     *
     * @throws ValidationException when the request cannot be carried out any more
     */
    public function approve(TeamRequest $request, User $admin, string $delivery = self::DELIVER_PASSWORD): Approval
    {
        $this->ensurePending($request);

        $approval = DB::transaction(function () use ($request, $admin, $delivery) {
            $approval = match ($request->type) {
                TeamRequest::NEW_MEMBER => $this->createAccount($request, $delivery),
                TeamRequest::ADD_MEMBER => $this->carryOutAdd($request),
                TeamRequest::REMOVE_MEMBER => $this->carryOutRemove($request),
            };

            // Sent inside the transaction: if the mail server fails, nothing is created and the request stays open.
            if ($approval->delivery === self::DELIVER_EMAIL) {
                try {
                    $approval->user->notify(new AccountInvite($request->requester));
                } catch (Throwable $exception) {
                    Log::warning('Could not send an account invitation.', ['email' => $request->email, 'error' => $exception->getMessage()]);

                    throw ValidationException::withMessages(['delivery' => 'The invitation email could not be sent, so nothing was changed. Try again or use a temporary password.']);
                }
            }

            $request->update(['status' => TeamRequest::APPROVED, 'decided_by' => $admin->getKey(), 'decided_at' => now()]);

            return $approval;
        });

        $this->push(collect([$request->requester]), new TeamRequestDecided($request));

        return $approval;
    }

    public function reject(TeamRequest $request, User $admin, ?string $reason = null): void
    {
        $this->ensurePending($request);

        $request->update([
            'status' => TeamRequest::REJECTED,
            'decided_by' => $admin->getKey(),
            'decided_at' => now(),
            'decision_note' => $reason,
        ]);

        $this->push(collect([$request->requester]), new TeamRequestDecided($request));
    }

    // ---------------------------------------------------------------- checking a new request

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function newMember(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = Str::lower(trim((string) ($data['email'] ?? '')));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Give the name and a valid email address of the new person.']);
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'There is already an account for '.$email.'. Use "Add an existing user" instead.']);
        }

        if (TeamRequest::query()->where('type', TeamRequest::NEW_MEMBER)->where('status', TeamRequest::PENDING)->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account for '.$email.' has already been requested.']);
        }

        return ['name' => $name, 'email' => $email];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function addMember(Team $team, array $data): array
    {
        $email = Str::lower(trim((string) ($data['email'] ?? '')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'There is no account with that email address. Request a new account instead.']);
        }

        if ($team->hasMember($user)) {
            throw ValidationException::withMessages(['email' => $user->name.' is already in this team.']);
        }

        $this->ensureNoPending($team, TeamRequest::ADD_MEMBER, $user, 'email');

        return ['target_user_id' => $user->getKey()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function removeMember(Team $team, array $data): array
    {
        $user = User::query()->find($data['user_id'] ?? null);

        if (! $user || ! $team->hasMember($user)) {
            throw ValidationException::withMessages(['user_id' => 'That person is not in this team.']);
        }

        if ($team->isManagedBy($user)) {
            throw ValidationException::withMessages(['user_id' => 'The manager cannot be removed from their own team.']);
        }

        $this->ensureNoPending($team, TeamRequest::REMOVE_MEMBER, $user, 'user_id');

        return ['target_user_id' => $user->getKey()];
    }

    private function ensureNoPending(Team $team, string $type, User $user, string $field): void
    {
        $exists = TeamRequest::query()
            ->where('team_id', $team->getKey())
            ->where('type', $type)
            ->where('target_user_id', $user->getKey())
            ->where('status', TeamRequest::PENDING)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([$field => 'There is already a pending request for '.$user->name.'.']);
        }
    }

    // ---------------------------------------------------------------- carrying out an approval

    private function ensurePending(TeamRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'This request was already '.$request->status.'.']);
        }
    }

    private function createAccount(TeamRequest $request, string $delivery): Approval
    {
        if (User::query()->whereRaw('lower(email) = ?', [Str::lower((string) $request->email)])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account for '.$request->email.' exists by now.']);
        }

        if (! in_array($delivery, [self::DELIVER_PASSWORD, self::DELIVER_EMAIL], true)) {
            throw new InvalidArgumentException('Unknown delivery: '.$delivery);
        }

        if ($delivery === self::DELIVER_EMAIL && ! MailSettings::canDeliver()) {
            throw ValidationException::withMessages(['delivery' => 'The app cannot send email yet. Use a temporary password instead.']);
        }

        $temporary = $delivery === self::DELIVER_PASSWORD ? Str::password(14, symbols: false) : null;

        $user = User::query()->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $temporary ?? Str::random(48),
            'role_id' => Role::query()->where('name', Role::USER)->value('id'),
            'must_change_password' => $temporary !== null,
            'invited_at' => $delivery === self::DELIVER_EMAIL ? now() : null,
        ]);

        $this->teams->addMember($request->team, $user);
        $request->update(['target_user_id' => $user->getKey()]);

        return new Approval($user, $temporary, $delivery);
    }

    private function carryOutAdd(TeamRequest $request): Approval
    {
        $this->teams->addMember($request->team, $request->target);

        return new Approval($request->target);
    }

    private function carryOutRemove(TeamRequest $request): Approval
    {
        try {
            $this->teams->removeMember($request->team, $request->target);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return new Approval($request->target);
    }

    /**
     * A push notification must never make an approval or a request fail.
     *
     * @param  iterable<User>  $users
     */
    private function push(iterable $users, object $notification): void
    {
        foreach ($users as $user) {
            try {
                $user->notify($notification);
            } catch (Throwable $exception) {
                Log::warning('Could not send a team request notification.', ['user' => $user->getKey(), 'error' => $exception->getMessage()]);
            }
        }
    }
}
