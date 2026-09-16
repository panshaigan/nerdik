<?php

namespace App\Filament\Admin\Resources\EventSeries\Pages;

use App\Filament\Admin\Resources\EventSeries\EventSeriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventSeries extends CreateRecord
{
    protected static string $resource = EventSeriesResource::class;
}
