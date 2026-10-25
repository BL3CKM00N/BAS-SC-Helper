<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The email with the link where a new user chooses their password.
 */
class AccountInvite extends Notification
{
    public const VALID_DAYS = 7;

    public function __construct(public readonly ?User $invitedBy = null) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('invite.show', now()->addDays(self::VALID_DAYS), ['user' => $notifiable->getKey()]);

        return (new MailMessage)
            ->subject('Your BAS SC Helper account')
            ->greeting('Hi '.$notifiable->name.',')
            ->line(($this->invitedBy ? $this->invitedBy->name.' asked for' : 'There is').' an account for you on BAS SC Helper.')
            ->action('Choose your password', $url)
            ->line('This link works for '.self::VALID_DAYS.' days and can be used once. After that you sign in with this email address and your password.');
    }
}
