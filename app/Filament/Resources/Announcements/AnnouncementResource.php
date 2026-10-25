<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        $timezone = auth()->user()?->timezone ?: config('app.timezone');

        return $schema->components([
            TextInput::make('title')->required()->maxLength(120),
            Textarea::make('body')->rows(3)->maxLength(1000),
            Select::make('severity')
                ->options([Announcement::INFO => 'Information', Announcement::WARNING => 'Warning', Announcement::CRITICAL => 'Critical (cannot be dismissed)'])
                ->default(Announcement::INFO)
                ->required()
                ->native(false),
            Select::make('audience')
                ->label('Who is it for')
                ->options([Announcement::EVERYONE => 'Everyone', Announcement::TEAM => 'One team', Announcement::MANAGERS => 'All team managers'])
                ->default(Announcement::EVERYONE)
                ->required()
                ->live()
                ->native(false),
            Select::make('team_id')
                ->label('Team')
                ->relationship('team', 'name')
                ->visible(fn ($get): bool => $get('audience') === Announcement::TEAM)
                ->required(fn ($get): bool => $get('audience') === Announcement::TEAM)
                ->native(false),
            DateTimePicker::make('starts_at')->label('Shows from')->helperText('Empty: right away.')->seconds(false)->timezone($timezone),
            DateTimePicker::make('ends_at')->label('Shows until')->helperText('Empty: until you remove it.')->seconds(false)->timezone($timezone)->after('starts_at'),
            Toggle::make('push')->label('Also send a push notification')->helperText('Sent once, as soon as the announcement starts.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('severity')->badge()->color(fn (string $state): string => match ($state) {
                    Announcement::CRITICAL => 'danger',
                    Announcement::WARNING => 'warning',
                    default => 'info',
                }),
                TextColumn::make('audience')->formatStateUsing(fn (string $state, Announcement $record): string => match ($state) {
                    Announcement::TEAM => $record->team?->name ?? 'One team',
                    Announcement::MANAGERS => 'Team managers',
                    default => 'Everyone',
                }),
                TextColumn::make('status')
                    ->state(fn (Announcement $record): string => $record->isActive() ? 'Showing' : ($record->starts_at?->isFuture() ? 'Scheduled' : 'Ended'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Showing' ? 'success' : 'gray'),
                IconColumn::make('push')->label('Push')->boolean()->trueIcon(Heroicon::OutlinedBellAlert),
                TextColumn::make('ends_at')->label('Until')->dateTime('d M H:i')->placeholder('-')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
