<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The push notification for a reminder that is due. The Done and Snooze buttons call signed URLs,
 * so they work from the notification without opening the app.
 */
class ReminderDue extends Notification
{
    public const SNOOZE_MINUTES = 10;

    public function __construct(public readonly ReminderRecipient $recipient) {}

    /**
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->pushSubscriptions()->exists() ? [WebPushChannel::class] : [];
    }

    public function toWebPush(User $notifiable, Notification $notification): WebPushMessage
    {
        $reminder = $this->recipient->reminder;
        $expires = now()->addHours(12);

        return (new WebPushMessage)
            ->title($reminder->title)
            ->icon('/icons/icon-192.png')
            ->body($this->body($reminder, $notifiable))
            ->tag('reminder-'.$reminder->getKey())
            ->renotify()
            ->options(['TTL' => 3600])
            ->data([
                'url' => route('reminders.index'),
                'doneUrl' => URL::temporarySignedRoute('push.done', $expires, ['recipient' => $this->recipient->getKey()]),
                'snoozeUrl' => URL::temporarySignedRoute('push.snooze', $expires, ['recipient' => $this->recipient->getKey()]),
            ])
            ->action('Done', 'done')
            ->action('Snooze '.self::SNOOZE_MINUTES.' min', 'snooze');
    }

    private function body(Reminder $reminder, User $user): string
    {
        $team = $reminder->team ? $reminder->team->name.' - ' : '';

        if ($reminder->isDeadline() && $reminder->due_at) {
            $due = $user->local($reminder->due_at)->format('D j M H:i');

            return $team.($reminder->due_at->isPast() ? 'Overdue since ' : 'Due ').$due;
        }

        return $team.($reminder->isScheduled() ? $reminder->scheduleLabel() : 'Time for the next round');
    }
}
