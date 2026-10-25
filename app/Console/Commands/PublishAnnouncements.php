<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Notifications\AnnouncementPublished;
use App\Services\Admin\SystemHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('announcements:publish')]
#[Description('Send the push notification of announcements that have started and ask for one')]
class PublishAnnouncements extends Command
{
    public function handle(SystemHealth $health): int
    {
        $sent = 0;

        Announcement::query()->active()->where('push', true)->whereNull('pushed_at')->each(function (Announcement $announcement) use ($health, &$sent) {
            // Marked first, so a slow or failing push service can never send the same announcement twice.
            $announcement->forceFill(['pushed_at' => now()])->save();

            $announcement->recipients()->each(function ($user) use ($announcement, $health, &$sent) {
                try {
                    $user->notify(new AnnouncementPublished($announcement));
                    $sent++;
                } catch (Throwable $exception) {
                    $health->pushFailed($exception->getMessage());
                }
            });
        });

        $this->info("Sent {$sent} announcement notification(s).");

        return self::SUCCESS;
    }
}
