<?php

namespace App\Filament\Pages;

use App\Services\Admin\SystemHealth;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SystemInfo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'System info';

    protected string $view = 'filament.pages.system-info';

    /**
     * @return array<string, string>
     */
    public function facts(): array
    {
        $beat = app(SystemHealth::class)->lastBeat();

        return [
            'App version' => (string) (config('app.version') ?: 'unknown'),
            'Environment' => app()->environment(),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Database' => DB::connection()->getDriverName(),
            'Queue' => (string) config('queue.default'),
            'Cache' => (string) config('cache.default'),
            'Sessions' => (string) config('session.driver'),
            'Mail' => (string) config('mail.default'),
            'Time zone' => (string) config('app.timezone'),
            'Scheduler last beat' => $beat ? $beat->format('d M Y H:i:s').' ('.$beat->diffForHumans().')' : 'never',
        ];
    }
}
