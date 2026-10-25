<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything about you rather than your work: your details, notifications, work hours and security.
 */
class AccountController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('account.profile');
    }

    public function profile(Request $request): View
    {
        $user = $request->user()->load('role');

        return view('account.profile', [
            'user' => $user,
            'teams' => $user->teams()->orderBy('name')->get(),
        ]);
    }

    public function notifications(): View
    {
        return view('account.notifications');
    }
}
