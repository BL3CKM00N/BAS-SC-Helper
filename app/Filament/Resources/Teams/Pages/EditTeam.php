<?php

namespace App\Filament\Resources\Teams\Pages;

use App\Filament\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamManagement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignManager')
                ->label('Assign manager')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (Team $record): bool => $record->isOpen())
                ->modalDescription('The person becomes a team manager and takes over this team. A manager can only manage one team.')
                ->schema([
                    Select::make('user_id')
                        ->label('New manager')
                        ->options(fn () => User::query()
                            ->whereDoesntHave('managedTeam')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (User $user) => [$user->getKey() => $user->name.' ('.$user->email.')']))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data, Team $record, TeamManagement $teams): void {
                    try {
                        $teams->assignManager($record, User::query()->findOrFail($data['user_id']));
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    $this->refreshFormData(['manager_id']);
                    Notification::make()->success()->title('Manager assigned')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
