<?php

namespace App\Filament\Pages;

use App\Services\Admin\AppDefaults;
use BackedEnum;
use DateTimeZone;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AppDefaultsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'App defaults';

    protected static ?string $title = 'App defaults';

    protected string $view = 'filament.pages.app-defaults';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(AppDefaults $defaults): void
    {
        $values = $defaults->all();
        $values['work_days'] = array_map('intval', explode(',', $values['work_days']));

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('New accounts')
                    ->description('What a new account starts with. People change their own work hours on their account page. Existing accounts are not changed.')
                    ->schema([
                        Select::make('timezone')
                            ->options(collect(DateTimeZone::listIdentifiers())->mapWithKeys(fn (string $zone) => [$zone => $zone])->all())
                            ->searchable()
                            ->required(),
                        CheckboxList::make('work_days')
                            ->options([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'])
                            ->columns(4)
                            ->gridDirection('row')
                            ->required()
                            ->minItems(1),
                        TimePicker::make('work_start')->seconds(false)->required(),
                        TimePicker::make('work_end')->seconds(false)->required()->after('work_start'),
                    ])->columns(2),
                Section::make('New teams')
                    ->description('Existing teams are not changed.')
                    ->schema([
                        Toggle::make('members_can_create')->label('Members may create team reminders'),
                    ]),
            ]);
    }

    public function save(AppDefaults $defaults): void
    {
        $data = $this->form->getState();

        $data['work_days'] = collect($data['work_days'])->map(fn ($day) => (int) $day)->unique()->sort()->implode(',');
        $data['work_start'] = substr((string) $data['work_start'], 0, 5);
        $data['work_end'] = substr((string) $data['work_end'], 0, 5);

        $defaults->save($data);

        Notification::make()->success()->title('Defaults saved')->send();
    }
}
