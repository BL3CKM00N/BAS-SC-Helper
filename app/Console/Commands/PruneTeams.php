<?php

namespace App\Console\Commands;

use App\Services\Teams\TeamManagement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('teams:prune')]
#[Description('Delete open teams that have no members left')]
class PruneTeams extends Command
{
    public function handle(TeamManagement $teams): int
    {
        $this->info('Removed '.$teams->pruneAbandoned().' empty team(s).');

        return self::SUCCESS;
    }
}
