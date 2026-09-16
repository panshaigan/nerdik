<?php

namespace App\Filament\Admin\Resources\EventSeries\Pages;

use App\Filament\Admin\Resources\EventSeries\EventSeriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventSeries extends ListRecords
{
    protected static string $resource = EventSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
