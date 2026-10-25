<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AnnouncementPublished extends Notification
{
    public function __construct(public readonly Announcement $announcement) {}

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
            ->title($this->announcement->title)
            ->icon('/icons/icon-192.png')
            ->body((string) str($this->announcement->body)->limit(140))
            ->tag('announcement-'.$this->announcement->getKey())
            ->options(['TTL' => 3600])
            ->data(['url' => route('dashboard')]);
    }
}
