<?php

namespace App\Filament\Widgets;

use App\Services\Admin\SystemHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NotificationChannels\WebPush\PushSubscription;

class SystemHealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'System health';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $health = app(SystemHealth::class);
        $beat = $health->lastBeat();
        $alive = $health->schedulerIsAlive();
        $failure = $health->lastPushFailure();

        return [
            Stat::make('Reminder scheduler', $alive ? 'Running' : 'Stopped')
                ->description($beat ? 'Last beat '.$beat->diffForHumans() : 'No beat seen yet')
                ->color($alive ? 'success' : 'danger'),
            Stat::make('Database', $health->databaseIsUp() ? 'Up' : 'Down')
                ->color($health->databaseIsUp() ? 'success' : 'danger'),
            Stat::make('Push devices', PushSubscription::query()->count())
                ->description(config('webpush.vapid.public_key') ? 'Push is configured' : 'Push keys are missing')
                ->color(config('webpush.vapid.public_key') ? 'success' : 'warning'),
            Stat::make('Failed pushes', $failure['count'] ?? 0)
                ->description($failure ? 'Last '.$failure['at']->diffForHumans() : 'None since start')
                ->color($failure ? 'warning' : 'success'),
        ];
    }
}
