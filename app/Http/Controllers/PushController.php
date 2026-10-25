<?php

namespace App\Http\Controllers;

use App\Models\ReminderRecipient;
use App\Notifications\ReminderDue;
use App\Services\Reminders\ReminderEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1000'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? null,
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }

    public function test(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->pushSubscriptions()->exists(), 422, 'Turn on notifications on this device first.');

        $user->notify(new class extends Notification
        {
            /** @return list<class-string> */
            public function via(): array
            {
                return [WebPushChannel::class];
            }

            public function toWebPush(): WebPushMessage
            {
                return (new WebPushMessage)
                    ->title('Notifications are on')
                    ->icon('/icons/icon-192.png')
                    ->body('You will get your reminders here.')
                    ->tag('test')
                    ->data(['url' => route('reminders.index')]);
            }
        });

        return response()->json(['status' => 'sent']);
    }

    /**
     * The Done button on a notification. The signed URL is the permission.
     */
    public function done(ReminderRecipient $recipient, ReminderEngine $engine): Response
    {
        if ($recipient->completed_at === null && $recipient->reminder->isActive()) {
            $engine->done($recipient);
        }

        return response()->noContent();
    }

    /**
     * The Snooze button on a notification.
     */
    public function snooze(ReminderRecipient $recipient, ReminderEngine $engine): Response
    {
        if ($recipient->completed_at === null && $recipient->reminder->isActive()) {
            $engine->snooze($recipient, ReminderDue::SNOOZE_MINUTES);
        }

        return response()->noContent();
    }
}
