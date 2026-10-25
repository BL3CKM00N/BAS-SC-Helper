<?php

namespace App\Http\Controllers;

use App\Services\Admin\Maintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (Auth::user()->isDisabled()) {
            Auth::logout();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'This account has been disabled. Ask an admin if you think that is a mistake.']);
        }

        if (app(Maintenance::class)->isOn() && ! Auth::user()->isAdmin()) {
            Auth::logout();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'Only admins can sign in during maintenance. '.app(Maintenance::class)->textFor(null)]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
