<?php

namespace App\Services\Reminders;

use App\Models\ReminderRecipient;
use App\Notifications\ReminderDue;
use App\Services\Admin\SystemHealth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a due reminder to the recipient's devices as a web push notification.
 */
class ReminderNotifier
{
    public function send(ReminderRecipient $recipient): void
    {
        if (! config('webpush.vapid.public_key')) {
            return;
        }

        try {
            $recipient->user->notify(new ReminderDue($recipient));
        } catch (Throwable $exception) {
            // A broken push service must never stop the other reminders from going out.
            app(SystemHealth::class)->pushFailed($exception->getMessage());
            Log::warning('Could not send a reminder push notification.', ['recipient' => $recipient->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}
