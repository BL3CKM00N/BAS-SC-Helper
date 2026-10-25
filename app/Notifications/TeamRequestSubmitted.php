<?php

namespace App\Notifications;

use App\Models\TeamRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells admins that a team manager sent a request.
 */
class TeamRequestSubmitted extends Notification
{
    public function __construct(public readonly TeamRequest $request) {}

    /**
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->pushSubscriptions()->exists() ? [WebPushChannel::class] : [];
    }

    public function toWebPush(User $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New team request')
            ->icon('/icons/icon-192.png')
            ->body($this->request->team->name.': '.$this->request->summary())
            ->tag('team-request-'.$this->request->getKey())
            ->data(['url' => url('/admin/team-requests')]);
    }
}
