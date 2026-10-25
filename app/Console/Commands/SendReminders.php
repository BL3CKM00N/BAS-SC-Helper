<?php

namespace App\Console\Commands;

use App\Services\Admin\SystemHealth;
use App\Services\Reminders\ReminderEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:send')]
#[Description('Send the reminders whose time has come')]
class SendReminders extends Command
{
    public function handle(ReminderEngine $engine, SystemHealth $health): int
    {
        // The heartbeat the admin dashboard looks at: if this stops, the scheduler is not running.
        $health->beat();

        $sent = $engine->sendDue();

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
