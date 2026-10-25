<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Services\Account\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Changing your own password and managing the devices you are signed in on.
 */
class AccountSecurityController extends Controller
{
    public function __construct(private readonly UserSessions $sessions) {}

    public function show(Request $request): View
    {
        return view('account.security', [
            'user' => $request->user(),
            'devices' => $this->sessions->for($request->user(), $request->session()->getId()),
        ]);
    }

    /**
     * Change the password. Every other device is signed out, this one stays signed in.
     */
    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = $request->user();
        $current = $request->session()->getId();

        $user->update(['password' => $data['password']]);

        // Keeps this session valid with the new password and invalidates the "stay signed in" cookies.
        Auth::logoutOtherDevices($data['password']);
        $this->sessions->rotateRememberToken($user);
        $this->sessions->revokeOthers($user, $current);
        $this->refreshRememberCookie($request);

        return redirect()->route('account.security')->with('status', 'Your password was changed and your other devices were signed out.');
    }

    /**
     * Sign out one other device.
     */
    public function revoke(Request $request, string $handle): RedirectResponse
    {
        $user = $request->user();

        if (! $this->sessions->revoke($user, $handle, $request->session()->getId())) {
            return redirect()->route('account.security')->withErrors(['devices' => 'That device was not found. It may already be signed out.']);
        }

        $this->sessions->rotateRememberToken($user);
        $this->refreshRememberCookie($request);

        return redirect()->route('account.security')->with('status', 'That device was signed out.');
    }

    /**
     * Sign out every device except this one.
     */
    public function revokeOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ended = $this->sessions->revokeOthers($user, $request->session()->getId());

        $this->sessions->rotateRememberToken($user);
        $this->refreshRememberCookie($request);

        return redirect()->route('account.security')->with('status', $ended > 0
            ? 'All your other devices were signed out.'
            : 'You were not signed in anywhere else.');
    }

    /**
     * Sign out everywhere, including this device.
     */
    public function logoutEverywhere(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $this->sessions->revokeAll($user);
        $this->sessions->rotateRememberToken($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Rotating the remember token would also sign this device out when it only has the "stay signed in" cookie,
     * so give this device a fresh cookie when it had one.
     */
    private function refreshRememberCookie(Request $request): void
    {
        if ($request->hasCookie(Auth::guard()->getRecallerName())) {
            Auth::login($request->user(), true);
        }
    }
}
