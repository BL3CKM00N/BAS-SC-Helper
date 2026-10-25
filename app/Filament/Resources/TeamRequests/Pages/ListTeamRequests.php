<?php

namespace App\Filament\Resources\TeamRequests\Pages;

use App\Filament\Resources\TeamRequests\TeamRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamRequests extends ListRecords
{
    protected static string $resource = TeamRequestResource::class;
}
