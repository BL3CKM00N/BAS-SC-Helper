<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        return view('teams.index', [
            'teams' => $request->user()->teams()->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function show(Team $team): View
    {
        Gate::authorize('view', $team);

        return view('teams.show', [
            'team' => $team,
            'members' => $team->users()->orderBy('name')->get(),
            'canManage' => Gate::allows('manageMembers', $team),
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->update(['members_can_create' => $request->boolean('members_can_create')]);

        return back()->with('status', 'Team settings saved.');
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in([Team::MEMBER, Team::MANAGER])],
        ], ['email.exists' => 'There is no user with that email address.']);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        if ($team->hasMember($user)) {
            return back()->withErrors(['email' => $user->name.' is already in this team.']);
        }

        $team->users()->attach($user, ['role' => $data['role']]);

        return back()->with('status', $user->name.' was added to the team.');
    }

    public function updateMember(Request $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $data = $request->validate(['role' => ['required', Rule::in([Team::MEMBER, Team::MANAGER])]]);

        abort_unless($team->hasMember($user), 404);

        if ($data['role'] === Team::MEMBER && $this->isLastManager($team, $user)) {
            return back()->withErrors(['role' => 'A team needs at least one manager.']);
        }

        $team->users()->updateExistingPivot($user->getKey(), ['role' => $data['role']]);

        return back()->with('status', $user->name.' is now a '.$data['role'].'.');
    }

    public function removeMember(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        abort_unless($team->hasMember($user), 404);

        if ($this->isLastManager($team, $user)) {
            return back()->withErrors(['role' => 'A team needs at least one manager.']);
        }

        $team->users()->detach($user);

        return back()->with('status', $user->name.' was removed from the team.');
    }

    private function isLastManager(Team $team, User $user): bool
    {
        return $team->isManagedBy($user)
            && $team->users()->wherePivot('role', Team::MANAGER)->count() === 1;
    }
}
