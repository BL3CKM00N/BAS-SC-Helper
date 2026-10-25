<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Resources\Teams\TeamResource;
use App\Services\Admin\Audit;
use App\Services\Admin\CsvExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export teams')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->action(function (CsvExport $export) {
                    Audit::record('team.exported', 'Exported the list of teams as CSV');

                    return response()->streamDownload(fn () => print ($export->teams()), 'teams-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
                }),
            CreateAction::make(),
        ];
    }
}
