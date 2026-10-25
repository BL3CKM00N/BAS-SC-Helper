<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TeamRequests\TeamRequestResource;
use App\Filament\Resources\Teams\TeamResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Team;
use App\Models\TeamRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NeedsAttention extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Needs attention';

    protected function getStats(): array
    {
        $pending = TeamRequest::query()->where('status', TeamRequest::PENDING)->count();
        $open = Team::query()->whereNull('manager_id')->count();
        $noTeam = User::query()->whereDoesntHave('teams')->count();
        $invites = User::query()->whereNotNull('invited_at')->count();
        $temporary = User::query()->where('must_change_password', true)->count();

        return [
            $this->stat('Pending requests', $pending, TeamRequestResource::getUrl()),
            $this->stat('Open teams', $open, TeamResource::getUrl()),
            $this->stat('People without a team', $noTeam, UserResource::getUrl()),
            $this->stat('Invitations not used yet', $invites, UserResource::getUrl()),
            $this->stat('Temporary passwords not changed', $temporary, UserResource::getUrl()),
        ];
    }

    private function stat(string $label, int $value, string $url): Stat
    {
        return Stat::make($label, $value)
            ->color($value > 0 ? 'warning' : 'success')
            ->description($value > 0 ? 'Take a look' : 'All clear')
            ->url($url);
    }
}
