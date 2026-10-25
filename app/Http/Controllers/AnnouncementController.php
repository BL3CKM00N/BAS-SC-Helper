<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class AnnouncementController extends Controller
{
    /**
     * The banners for the signed-in person, as a fragment the page refreshes now and then.
     */
    public function index(Request $request): View
    {
        return view('layouts._announcements', ['announcements' => $this->visibleTo($request)]);
    }

    public function dismiss(Request $request, Announcement $announcement): RedirectResponse|Response
    {
        abort_unless($announcement->isDismissable() && $this->visibleTo($request)->contains($announcement), 403);

        $announcement->dismissedBy()->syncWithoutDetaching([$request->user()->getKey()]);

        return $request->expectsJson() ? response()->noContent() : back();
    }

    /**
     * @return Collection<int, Announcement>
     */
    private function visibleTo(Request $request): Collection
    {
        return Announcement::visibleFor($request->user());
    }
}
