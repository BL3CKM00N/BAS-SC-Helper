<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserActions;
use App\Models\Role;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
