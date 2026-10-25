<?php

namespace App\Filament\Pages;

use App\Models\Team;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * How the teams are doing with their team reminders. Only team reminders are counted: personal reminders are
 * private and never show up here.
 */
class TeamActivity extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.table-page';

    public function getTitle(): string
    {
        return 'Team activity';
    }

    public function table(Table $table): Table
    {
        $open = fn ($query) => $query->whereNull('archived_at')->whereNull('completed_at');

        return $table
            ->query(Team::query()
                ->with('manager')
                ->withCount([
                    'users as members_count',
                    'reminders as open_count' => $open,
                    'reminders as overdue_count' => fn ($query) => $open($query)->where('due_at', '<', now()),
                    'reminders as done_week_count' => fn ($query) => $query->where('completed_at', '>=', now()->subDays(7)),
                ]))
            ->defaultSort('overdue_count', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Team $record): string => 'Manager: '.($record->manager?->name ?? 'none')),
                TextColumn::make('members_count')->label('Members')->sortable(),
                TextColumn::make('open_count')->label('Open reminders')->sortable(),
                TextColumn::make('overdue_count')->label('Overdue')->sortable()->badge()->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('done_week_count')->label('Done, last 7 days')->sortable(),
            ])
            ->emptyStateHeading('No teams yet');
    }
}
