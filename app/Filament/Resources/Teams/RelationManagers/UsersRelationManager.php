<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Services\Teams\TeamManagement;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('manager')
                    ->state(fn ($record): ?string => $this->getOwnerRecord()->isManagedBy($record) ? 'manager of this team' : null)
                    ->badge()
                    ->color('primary'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                // The manager belongs to their own team, they are removed by changing who manages it.
                DetachAction::make()
                    ->hidden(fn ($record): bool => $this->getOwnerRecord()->isManagedBy($record))
                    ->after(fn () => $this->leaveIfTeamIsGone()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->after(fn () => $this->leaveIfTeamIsGone()),
                ]),
            ]);
    }

    /**
     * An open team with nobody left is deleted, so this page has nothing to show any more.
     */
    private function leaveIfTeamIsGone(): void
    {
        app(TeamManagement::class)->pruneAbandoned();

        if (! Team::query()->whereKey($this->getOwnerRecord()->getKey())->exists()) {
            $this->redirect(TeamResource::getUrl('index'));
        }
    }
}
