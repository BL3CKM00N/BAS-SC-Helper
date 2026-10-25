<?php

namespace App\Filament\Resources\TeamRequests;

use App\Filament\Resources\TeamRequests\Pages\ListTeamRequests;
use App\Models\TeamRequest;
use App\Services\Teams\TeamRequestService;
use App\Support\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * Where admins approve or reject what team managers ask for.
 */
class TeamRequestResource extends Resource
{
    protected static ?string $model = TeamRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Team requests';

    protected static ?string $modelLabel = 'team request';

    protected static ?string $slug = 'team-requests';

    public static function getNavigationBadge(): ?string
    {
        $pending = TeamRequest::query()->where('status', TeamRequest::PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['team', 'requester', 'target', 'decider']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('D j M H:i')
                    ->sortable(),
                TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->label('Requested by')
                    ->searchable(),
                TextColumn::make('summary')
                    ->label('Request')
                    ->state(fn (TeamRequest $record): string => $record->summary())
                    ->wrap(),
                TextColumn::make('note')
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        TeamRequest::APPROVED => 'success',
                        TeamRequest::REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('decider.name')
                    ->label('Decided by')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('decision_note')
                    ->label('Reason')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        TeamRequest::PENDING => 'Pending',
                        TeamRequest::APPROVED => 'Approved',
                        TeamRequest::REJECTED => 'Rejected',
                    ])
                    ->default(TeamRequest::PENDING),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                static::approveAction(),
                static::rejectAction(),
            ]);
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (TeamRequest $record): bool => $record->isPending())
            ->modalHeading(fn (TeamRequest $record): string => 'Approve: '.$record->summary())
            ->modalDescription(fn (TeamRequest $record): string => match ($record->type) {
                TeamRequest::NEW_MEMBER => 'This creates the account and puts the person in '.$record->team->name.'.',
                TeamRequest::ADD_MEMBER => 'This adds the person to '.$record->team->name.'.',
                default => 'This removes the person from '.$record->team->name.'.',
            })
            ->schema(fn (TeamRequest $record): array => $record->type === TeamRequest::NEW_MEMBER ? [
                Select::make('delivery')
                    ->label('How does the new user get their password?')
                    ->options([
                        TeamRequestService::DELIVER_PASSWORD => 'Temporary password (shown to me once)',
                        TeamRequestService::DELIVER_EMAIL => 'Email invitation with a link',
                    ])
                    ->disableOptionWhen(fn (string $value): bool => $value === TeamRequestService::DELIVER_EMAIL && ! MailSettings::canDeliver())
                    ->helperText(MailSettings::canDeliver()
                        ? 'With an invitation the person chooses their own password from the link, valid for 7 days.'
                        : 'Email invitations need mail settings (MAIL_* in the environment). Until then use a temporary password.')
                    ->default(TeamRequestService::DELIVER_PASSWORD)
                    ->native(false)
                    ->required(),
            ] : [])
            ->action(function (TeamRequest $record, array $data, TeamRequestService $requests): void {
                try {
                    $approval = $requests->approve($record, auth()->user(), $data['delivery'] ?? TeamRequestService::DELIVER_PASSWORD);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Could not approve')->body(collect($exception->errors())->flatten()->first())->send();

                    return;
                }

                if ($approval->temporaryPassword) {
                    Notification::make()->success()->persistent()
                        ->title('Account created for '.$approval->user->name)
                        ->body('Give them this temporary password: '.$approval->temporaryPassword.'. It is shown only now. They choose their own password when they sign in.')
                        ->send();

                    return;
                }

                Notification::make()->success()
                    ->title($approval->delivery === TeamRequestService::DELIVER_EMAIL ? 'Invitation sent to '.$approval->user->email : 'Request approved')
                    ->send();
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (TeamRequest $record): bool => $record->isPending())
            ->modalHeading(fn (TeamRequest $record): string => 'Reject: '.$record->summary())
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (the manager will see it)')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (TeamRequest $record, array $data, TeamRequestService $requests): void {
                try {
                    $requests->reject($record, auth()->user(), $data['reason'] ?? null);
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Could not reject')->body(collect($exception->errors())->flatten()->first())->send();

                    return;
                }

                Notification::make()->success()->title('Request rejected')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamRequests::route('/'),
        ];
    }
}
