<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The notification behind "send a test notification", from the account page and from the admin panel.
 */
class TestPush extends Notification
{
    /**
     * @return list<class-string>
     */
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
}
