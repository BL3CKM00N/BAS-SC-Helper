<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Services\Admin\AccountAdmin;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(UserActions::all())->label('Account'),
            DeleteAction::make(),
        ];
    }

    /**
     * The last admin who can sign in keeps the role, otherwise nobody could reach this panel any more.
     */
    protected function beforeSave(): void
    {
        $newRole = Role::query()->whereKey($this->data['role_id'] ?? null)->value('name');

        if ($newRole !== Role::ADMIN && app(AccountAdmin::class)->isLastAdmin($this->record)) {
            Notification::make()->danger()->title('Role not changed')->body($this->record->name.' is the only admin who can still sign in.')->send();

            $this->halt();
        }
    }
}
