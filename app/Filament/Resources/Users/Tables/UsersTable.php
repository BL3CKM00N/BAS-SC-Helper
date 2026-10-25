<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserActions;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamManagement;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Role::ADMIN => 'primary',
                        Role::TEAM_MANAGER => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('disabled_at')
                    ->label('Active')
                    ->boolean()
                    ->state(fn ($record): bool => ! $record->isDisabled())
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('last_login_at')
                    ->label('Last sign-in')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role_id')->label('Role')->relationship('role', 'name'),
                SelectFilter::make('team')
                    ->label('Team')
                    ->relationship('teams', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('no_team')->label('Without a team')->query(fn (Builder $query) => $query->whereDoesntHave('teams')),
                Filter::make('disabled')->label('Disabled')->query(fn (Builder $query) => $query->whereNotNull('disabled_at')),
                Filter::make('never_signed_in')->label('Never signed in')->query(fn (Builder $query) => $query->whereNull('last_login_at')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                ActionGroup::make(UserActions::all()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addToTeam')
                        ->label('Add to team')
                        ->icon(Heroicon::UserPlus)
                        ->schema([Select::make('team_id')->label('Team')->options(fn () => Team::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required()])
                        ->action(function (Collection $records, array $data, TeamManagement $teams): void {
                            $team = Team::query()->findOrFail($data['team_id']);
                            $records->each(fn (User $user) => $teams->addMember($team, $user));
                            Notification::make()->success()->title($records->count().' people added to '.$team->name)->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('removeFromTeam')
                        ->label('Remove from team')
                        ->icon(Heroicon::UserMinus)
                        ->schema([Select::make('team_id')->label('Team')->options(fn () => Team::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required()])
                        ->action(function (Collection $records, array $data, TeamManagement $teams): void {
                            $team = Team::query()->findOrFail($data['team_id']);
                            $removed = 0;
                            $skipped = 0;

                            foreach ($records as $user) {
                                if (! $team->hasMember($user) || $team->isManagedBy($user)) {
                                    $skipped++;

                                    continue;
                                }

                                $teams->removeMember($team, $user);
                                $removed++;
                            }

                            Notification::make()->success()->title($removed.' removed from '.$team->name)->body($skipped ? $skipped.' skipped (not in the team, or its manager)' : null)->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
