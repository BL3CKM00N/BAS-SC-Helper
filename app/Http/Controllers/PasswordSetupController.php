<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Choosing a first password: after signing in with a temporary one, or from the link in an invitation email.
 */
class PasswordSetupController extends Controller
{
    public function changeForm(): View
    {
        return view('password.setup', [
            'title' => 'Choose your password',
            'intro' => 'You signed in with a temporary password. Choose your own to continue.',
            'action' => route('password.update'),
        ]);
    }

    public function change(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => $this->rules()]);

        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);

        return redirect()->route('dashboard')->with('status', 'Your password was saved.');
    }

    public function inviteForm(Request $request, User $user): View
    {
        $this->ensureInviteIsOpen($user);

        return view('password.setup', [
            'title' => 'Welcome, '.$user->name,
            'intro' => 'Choose a password for '.$user->email.'.',
            'action' => $request->fullUrl(),
        ]);
    }

    public function invite(Request $request, User $user): RedirectResponse
    {
        $this->ensureInviteIsOpen($user);

        $data = $request->validate(['password' => $this->rules()]);

        $user->forceFill([
            'password' => $data['password'],
            'invited_at' => null,
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome! Your password was saved.');
    }

    /**
     * The link can only be used once.
     */
    private function ensureInviteIsOpen(User $user): void
    {
        abort_if($user->invited_at === null, 403, 'This invitation was already used.');
    }

    /**
     * @return array<int, mixed>
     */
    private function rules(): array
    {
        return ['required', 'confirmed', Password::min(10)];
    }
}
