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

        return array_filter([
            'App version' => $this->version(),
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
        ]);
    }

    /**
     * The APP_VERSION environment variable, or the short git commit when the app runs from a checkout.
     */
    private function version(): ?string
    {
        if ($version = env('APP_VERSION')) {
            return (string) $version;
        }

        $head = @file_get_contents(base_path('.git/HEAD'));

        if ($head === false) {
            return null;
        }

        $head = trim($head);
        $commit = str_starts_with($head, 'ref: ')
            ? @file_get_contents(base_path('.git/'.substr($head, 5)))
            : $head;

        return $commit ? substr(trim($commit), 0, 7) : null;
    }
}
