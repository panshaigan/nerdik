<?php

namespace App\Filament\Admin\Resources\ActivitySeries\Pages;

use App\Filament\Admin\Resources\ActivitySeries\ActivitySeriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivitySeries extends ListRecords
{
    protected static string $resource = ActivitySeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
