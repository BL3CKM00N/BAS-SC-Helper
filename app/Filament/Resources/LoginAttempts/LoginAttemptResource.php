<?php

namespace App\Filament\Resources\LoginAttempts;

use App\Filament\Resources\LoginAttempts\Pages\ListLoginAttempts;
use App\Models\LoginAttempt;
use App\Support\DeviceInfo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class LoginAttemptResource extends Resource
{
    protected static ?string $model = LoginAttempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'sign-in';

    protected static ?string $pluralModelLabel = 'sign-in log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y H:i:s')->sortable(),
                IconColumn::make('successful')->label('')->boolean(),
                TextColumn::make('email')->label('Email address')->searchable()->description(fn (LoginAttempt $record): ?string => $record->reason),
                TextColumn::make('ip_address')->label('IP address')->searchable()->placeholder('-'),
                TextColumn::make('user_agent')->label('Device')->formatStateUsing(fn (?string $state): string => DeviceInfo::parse($state)['label']),
            ])
            ->filters([
                TernaryFilter::make('successful')->label('Result')->trueLabel('Succeeded')->falseLabel('Failed'),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginAttempts::route('/')];
    }
}
