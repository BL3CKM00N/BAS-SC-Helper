<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use App\Services\Admin\AccountAdmin;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * The account actions an admin has next to a person, shared by the users table and the edit page.
 */
class UserActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::disable(),
            self::enable(),
            self::resetPassword(),
            self::signOut(),
            self::resendInvite(),
            self::anonymise(),
        ];
    }

    private static function run(Closure $work, string $failTitle): void
    {
        try {
            $work();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($failTitle)->body($exception->getMessage())->send();
        }
    }

    public static function disable(): Action
    {
        return Action::make('disable')
            ->label('Disable account')
            ->icon(Heroicon::NoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('They are signed out everywhere and cannot sign in or get reminders until you enable the account again.')
            ->visible(fn (User $record): bool => ! $record->isDisabled())
            ->action(function (User $record, AccountAdmin $admin): void {
                self::run(function () use ($record, $admin) {
                    $admin->disable($record, auth()->user());
                    Notification::make()->success()->title($record->name.' was disabled')->send();
                }, 'Could not disable');
            });
    }

    public static function enable(): Action
    {
        return Action::make('enable')
            ->label('Enable account')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => $record->isDisabled() && $record->name !== 'Former user')
            ->action(function (User $record, AccountAdmin $admin): void {
                $admin->enable($record, auth()->user());
                Notification::make()->success()->title($record->name.' can sign in again')->send();
            });
    }

    public static function resetPassword(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon(Heroicon::Key)
            ->requiresConfirmation()
            ->modalDescription('Creates a temporary password that you hand to the person. They are signed out everywhere and must choose their own password when they sign in.')
            ->visible(fn (User $record): bool => ! $record->isDisabled())
            ->action(function (User $record, AccountAdmin $admin): void {
                $temporary = $admin->resetPassword($record, auth()->user());

                Notification::make()->success()->persistent()
                    ->title('New password for '.$record->name)
                    ->body('Give them this temporary password: '.$temporary.'. It is shown only now.')
                    ->send();
            });
    }

    public static function signOut(): Action
    {
        return Action::make('signOut')
            ->label('Sign out everywhere')
            ->icon(Heroicon::ArrowRightOnRectangle)
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => ! $record->isDisabled())
            ->action(function (User $record, AccountAdmin $admin): void {
                $admin->signOutEverywhere($record, auth()->user());
                Notification::make()->success()->title($record->name.' was signed out on all devices')->send();
            });
    }

    public static function resendInvite(): Action
    {
        return Action::make('resendInvite')
            ->label('Resend invitation')
            ->icon(Heroicon::Envelope)
            ->visible(fn (User $record): bool => $record->invited_at !== null && ! $record->isDisabled())
            ->action(function (User $record, AccountAdmin $admin): void {
                self::run(function () use ($record, $admin) {
                    $admin->resendInvite($record, auth()->user());
                    Notification::make()->success()->title('Invitation sent to '.$record->email)->send();
                }, 'Could not send');
            });
    }

    public static function anonymise(): Action
    {
        return Action::make('anonymise')
            ->label('Remove person')
            ->icon(Heroicon::UserMinus)
            ->color('danger')
            ->modalHeading('Remove this person')
            ->modalDescription('Their name and email are wiped, they leave all teams, and their devices and personal reminders are deleted. Team reminders and the audit log stay. This cannot be undone.')
            ->schema(fn (User $record): array => [
                TextInput::make('confirm')
                    ->label('Type '.$record->email.' to confirm')
                    ->required()
                    ->in([$record->email])
                    ->validationMessages(['in' => 'That is not the email address of this person.']),
            ])
            ->visible(fn (User $record): bool => $record->name !== 'Former user')
            ->action(function (User $record, AccountAdmin $admin): void {
                self::run(function () use ($record, $admin) {
                    $admin->anonymise($record, auth()->user());
                    Notification::make()->success()->title('The person was removed')->send();
                }, 'Could not remove');
            });
    }
}
