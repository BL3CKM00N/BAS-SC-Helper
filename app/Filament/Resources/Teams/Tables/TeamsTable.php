<?php

namespace App\Filament\Resources\Teams\Tables;

use App\Models\Team;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->state(fn (Team $team): string => $team->isOpen() ? 'Open' : 'Managed')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Open' ? 'warning' : 'success'),
                TextColumn::make('manager.name')
                    ->label('Manager')
                    ->placeholder('No manager'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members'),
                IconColumn::make('members_can_create')
                    ->label('Members can create')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('open')
                    ->label('Open (no manager)')
                    ->queries(
                        true: fn ($query) => $query->whereNull('manager_id'),
                        false: fn ($query) => $query->whereNotNull('manager_id'),
                    ),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
