<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        LoginAttempt::query()->create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => (string) ($event->credentials['email'] ?? ''),
            'successful' => false,
            'reason' => $event->user ? 'Wrong password' : 'Unknown email address',
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
