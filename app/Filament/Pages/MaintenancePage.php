<?php

namespace App\Filament\Pages;

use App\Services\Admin\Maintenance;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MaintenancePage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $title = 'Maintenance mode';

    protected string $view = 'filament.pages.maintenance';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(Maintenance $maintenance): void
    {
        $this->form->fill([
            'on' => $maintenance->isOn(),
            'message' => $maintenance->isOn() ? $maintenance->message() : null,
            'back_at' => $maintenance->isOn() ? $maintenance->backAt() : null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('on')
                    ->label('Maintenance mode is on')
                    ->helperText('Everybody except admins sees a maintenance page and cannot sign in. Reminders and push notifications keep being sent.'),
                Textarea::make('message')
                    ->label('Message')
                    ->placeholder(Maintenance::DEFAULT_MESSAGE)
                    ->rows(3)
                    ->maxLength(300),
                DateTimePicker::make('back_at')
                    ->label('Expected back')
                    ->seconds(false)
                    ->timezone(auth()->user()?->timezone ?: config('app.timezone')),
            ]);
    }

    public function save(Maintenance $maintenance): void
    {
        $data = $this->form->getState();

        if ($data['on']) {
            $maintenance->enable($data['message'] ?: null, $data['back_at'] ? CarbonImmutable::parse($data['back_at']) : null, auth()->user());
        } else {
            $maintenance->disable(auth()->user());
        }

        Notification::make()->success()->title($data['on'] ? 'Maintenance mode is on' : 'Maintenance mode is off')->send();
    }
}
