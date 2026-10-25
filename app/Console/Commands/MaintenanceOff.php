<?php

namespace App\Console\Commands;

use App\Services\Admin\Maintenance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maintenance:off')]
#[Description('Turn maintenance mode off')]
class MaintenanceOff extends Command
{
    public function handle(Maintenance $maintenance): int
    {
        $maintenance->disable();

        $this->info('Maintenance mode is off.');

        return self::SUCCESS;
    }
}
