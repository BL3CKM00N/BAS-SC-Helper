<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        return view('teams.index', [
            'teams' => $request->user()->teams()->with('manager')->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Team $team): View
    {
        Gate::authorize('view', $team);

        return view('teams.show', [
            'team' => $team->load('manager'),
            'members' => $team->users()->orderBy('name')->get(),
            'canManage' => Gate::allows('manageMembers', $team),
            'requests' => Gate::allows('manageMembers', $team)
                ? $team->requests()->with(['target', 'decider'])->latest()->limit(20)->get()
                : collect(),
        ]);
    }

    /**
     * The manager renames the team and decides whether members may create team reminders.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:teams,name,'.$team->getKey()],
        ]);

        $team->update($data + ['members_can_create' => $request->boolean('members_can_create')]);

        return back()->with('status', 'Team settings saved.');
    }
}
