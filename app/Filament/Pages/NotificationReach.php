<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\TestPush;
use App\Services\Admin\Audit;
use App\Services\Admin\SystemHealth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use NotificationChannels\WebPush\PushSubscription;
use Throwable;
use UnitEnum;

class NotificationReach extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.notification-reach';

    public function getTitle(): string
    {
        return 'Notification reach';
    }

    /**
     * @return array{people: int, reachable: int, devices: int}
     */
    public function totals(): array
    {
        $people = User::query()->whereNull('disabled_at')->count();

        return [
            'people' => $people,
            'reachable' => User::query()->whereNull('disabled_at')->whereHas('pushSubscriptions')->count(),
            'devices' => PushSubscription::query()->count(),
        ];
    }

    public function lastFailure(): ?array
    {
        return app(SystemHealth::class)->lastPushFailure();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->whereNull('disabled_at')->withCount('pushSubscriptions'))
            ->defaultSort('push_subscriptions_count')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $record): string => $record->email),
                TextColumn::make('push_subscriptions_count')->label('Devices')->sortable()->badge()->color(fn (int $state): string => $state > 0 ? 'success' : 'warning'),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->placeholder('Never')->sortable(),
            ])
            ->filters([
                Filter::make('none')->label('Without notifications')->query(fn (Builder $query) => $query->whereDoesntHave('pushSubscriptions'))->default(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Send test')
                    ->icon(Heroicon::PaperAirplane)
                    ->visible(fn (User $record): bool => $record->push_subscriptions_count > 0)
                    ->action(function (User $record): void {
                        try {
                            $record->notify(new TestPush);
                        } catch (Throwable $exception) {
                            app(SystemHealth::class)->pushFailed($exception->getMessage());
                            Notification::make()->danger()->title('The push service refused it')->body($exception->getMessage())->send();

                            return;
                        }

                        Audit::record('push.test', 'Sent a test notification to '.$record->name, $record);
                        Notification::make()->success()->title('Test notification sent to '.$record->name)->send();
                    }),
            ])
            ->emptyStateHeading('Everyone has notifications on');
    }
}
