<?php

namespace App\Filament\Pages;

use App\Services\Account\UserSessions;
use App\Services\Admin\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\ArrayRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class ActiveSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.table-page';

    public static function getNavigationLabel(): string
    {
        return 'Active sessions';
    }

    public function getTitle(): string
    {
        return 'Active sessions';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(UserSessions::class)->everyone(session()->getId())->all())
            ->paginated(false)
            ->poll('30s')
            ->columns([
                TextColumn::make('user')->label('Person')->description(fn (array $record): string => $record['email']),
                TextColumn::make('label')->label('Device')->description(fn (array $record): string => ucfirst($record['device'])),
                TextColumn::make('ip')->label('IP address')->placeholder('-'),
                TextColumn::make('last_active')->label('Last active')->since(),
                TextColumn::make('current')->label('')->badge()->state(fn (array $record): ?string => $record['current'] ? 'This device' : null),
            ])
            ->recordActions([
                Action::make('signOut')
                    ->label('Sign out')
                    ->icon(Heroicon::ArrowLeftStartOnRectangle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (array $record): bool => $record['current'])
                    ->action(function (array $record): void {
                        $user = app(UserSessions::class)->revokeHandle($record[ArrayRecord::getKeyName()], session()->getId());

                        if ($user) {
                            Audit::record('session.revoked', 'Ended a session of '.$user->name.' ('.$record['label'].')', $user);
                        }

                        Notification::make()->success()->title('Signed out')->send();
                    }),
            ])
            ->emptyStateHeading('Nobody is signed in');
    }
}
