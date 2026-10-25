<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();

        LoginAttempt::query()->create([
            'user_id' => $event->user->getKey(),
            'email' => $event->user->email,
            'successful' => ! $event->user->isDisabled(),
            'reason' => $event->user->isDisabled() ? 'Account is disabled' : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
