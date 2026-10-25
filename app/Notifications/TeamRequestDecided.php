<?php

namespace App\Notifications;

use App\Models\TeamRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells the team manager what an admin decided about their request.
 */
class TeamRequestDecided extends Notification
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
        $approved = $this->request->status === TeamRequest::APPROVED;

        return (new WebPushMessage)
            ->title($approved ? 'Request approved' : 'Request rejected')
            ->icon('/icons/icon-192.png')
            ->body($this->request->summary().($approved || ! $this->request->decision_note ? '' : ' - '.$this->request->decision_note))
            ->tag('team-request-'.$this->request->getKey())
            ->data(['url' => route('teams.show', $this->request->team_id)]);
    }
}
