<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Admin\Audit;
use App\Services\Admin\CsvExport;
use App\Services\Admin\UserImport;
use App\Support\MailSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([$this->importAction(), $this->exportAction()])->label('CSV')->icon(Heroicon::ArrowDownTray)->button()->color('gray'),
            CreateAction::make(),
        ];
    }

    private function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export people')
            ->icon(Heroicon::ArrowDownTray)
            ->action(function (CsvExport $export) {
                Audit::record('user.exported', 'Exported the list of people as CSV');

                return response()->streamDownload(fn () => print ($export->users()), 'people-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
            });
    }

    private function importAction(): Action
    {
        return Action::make('import')
            ->label('Import people')
            ->icon(Heroicon::ArrowUpTray)
            ->modalDescription('A CSV file with the columns name and email, and optionally role (user, team_manager or admin) and team (the name of an existing team). Semicolons work too.')
            ->schema([
                FileUpload::make('file')->label('CSV file')->disk('local')->directory('imports')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->maxSize(512)->required(),
                Select::make('delivery')
                    ->label('How do they get their password')
                    ->options(array_filter([
                        UserImport::DELIVER_PASSWORD => 'Temporary passwords (you get a file to hand them out)',
                        UserImport::DELIVER_EMAIL => MailSettings::canDeliver() ? 'Email invitation' : null,
                    ]))
                    ->default(UserImport::DELIVER_PASSWORD)
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data, UserImport $import) {
                $path = $data['file'];
                $csv = Storage::disk('local')->get($path);
                Storage::disk('local')->delete($path);

                try {
                    $result = $import->run((string) $csv, $data['delivery'], auth()->user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->danger()->title('Could not import')->body($exception->getMessage())->send();

                    return null;
                }

                $skipped = collect($result['skipped'])->map(fn (array $row) => 'Row '.$row['row'].' ('.$row['email'].'): '.$row['reason'])->take(10)->implode("\n");

                Notification::make()
                    ->title(count($result['created']).' account(s) created, '.count($result['skipped']).' skipped')
                    ->body($skipped ?: null)
                    ->color($result['skipped'] ? 'warning' : 'success')
                    ->persistent($result['skipped'] !== [])
                    ->send();

                if ($data['delivery'] !== UserImport::DELIVER_PASSWORD || $result['created'] === []) {
                    return null;
                }

                // The only moment the temporary passwords exist in plain text: hand them to the admin as a file.
                $export = app(CsvExport::class);
                $file = $export->build(['name', 'email', 'temporary_password'], array_map(fn (array $row) => [$row['name'], $row['email'], $row['password']], $result['created']));

                return response()->streamDownload(fn () => print ($file), 'new-accounts-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
            });
    }
}
