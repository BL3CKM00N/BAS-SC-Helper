<?php

namespace App\Console\Commands;

use App\Services\Admin\Maintenance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maintenance:on {--message= : What people see} {--minutes= : Expected duration in minutes}')]
#[Description('Turn maintenance mode on: everybody except admins is locked out, reminders keep being sent')]
class MaintenanceMode extends Command
{
    public function handle(Maintenance $maintenance): int
    {
        $minutes = $this->option('minutes');

        $maintenance->enable($this->option('message') ?: null, $minutes ? now()->addMinutes((int) $minutes) : null);

        $this->info('Maintenance mode is on. Turn it off with maintenance:off.');

        return self::SUCCESS;
    }
}
