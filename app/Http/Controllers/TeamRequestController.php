<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamRequest;
use App\Services\Teams\TeamRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamRequestController extends Controller
{
    public function store(Request $request, Team $team, TeamRequestService $requests): RedirectResponse
    {
        Gate::authorize('manageMembers', $team);

        $data = $request->validate([
            'type' => ['required', Rule::in([TeamRequest::NEW_MEMBER, TeamRequest::ADD_MEMBER, TeamRequest::REMOVE_MEMBER])],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $sent = $requests->submit($request->user(), $team, $data['type'], $data);

        return back()->with('status', 'Request sent to the admins: '.$sent->summary().'.');
    }

    /**
     * The manager withdraws a request that nobody has handled yet.
     */
    public function destroy(Request $request, Team $team, TeamRequest $teamRequest): RedirectResponse
    {
        abort_unless($teamRequest->team_id === $team->getKey(), 404);
        abort_unless($teamRequest->requested_by === $request->user()->getKey() && $teamRequest->isPending(), 403);

        $teamRequest->delete();

        return back()->with('status', 'Request withdrawn.');
    }
}
